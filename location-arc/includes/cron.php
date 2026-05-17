<?php
if (!defined('ABSPATH')) exit;

function locarc_cron_schedule() {
    if (!wp_next_scheduled('locarc_daily_check')) {
        wp_schedule_event(time() + 300, 'daily', 'locarc_daily_check');
    }
}
function locarc_cron_unschedule() {
    $ts = wp_next_scheduled('locarc_daily_check');
    if ($ts) wp_unschedule_event($ts, 'locarc_daily_check');
}

add_action('locarc_daily_check', 'locarc_daily_check_contracts');

function locarc_daily_check_contracts() {
    global $wpdb;
    $t = locarc_tables();

    $now = current_datetime();
    $today   = $now->format('Y-m-d');
    $target_7 = wp_date('Y-m-d', strtotime('+7 days', $now->getTimestamp()));

    // Settings
    $admin_to = get_option('locarc_alerts_admin_to', get_option('admin_email'));
    $enabled_unpaid = (get_option('locarc_alerts_unpaid_enabled', '0') === '1');
    $enabled_admin_exp = (get_option('locarc_alerts_admin_expiring_enabled', '0') === '1');
    $enabled_renter_exp = (get_option('locarc_alerts_renter_expiring_enabled', '0') === '1');

    // 1) Renter reminder: contracts ending in 7 days (one-shot per contract, by date match)
    if ($enabled_renter_exp) {
        $contracts = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, m.first_name, m.last_name
             FROM {$t['contracts']} c
             LEFT JOIN {$t['members']} m ON m.licence = c.licence
             WHERE c.status='active' AND c.end_date=%s",
             $target_7
        ), ARRAY_A);

        if ($contracts) {
            // Prime the name cache in a single query before the loop (avoids N+1).
            locarc_prime_member_names(array_column($contracts, 'licence'));

            $subj_tpl = get_option('locarc_reminder_subject', 'Votre contrat se termine bientôt');
            $body_tpl = get_option('locarc_reminder_body', "Bonjour {{prenom}},\n\nPetit rappel : votre contrat de location se termine le {{date_fin}}.\n\nCordialement,\nACSIM");

            foreach ($contracts as $c) {
                // Ensure names are filled
                if (trim((string)($c['first_name'] ?? '')) === '' && trim((string)($c['last_name'] ?? '')) === '') {
                    [$fn, $ln] = locarc_member_names($c['licence']);
                    $c['first_name'] = $fn;
                    $c['last_name']  = $ln;
                }

                $u = get_user_by('login', $c['licence']);
                if (!$u || empty($u->user_email)) continue;

                $vars = [
                    '{{prenom}}'          => (string)($c['first_name'] ?? ''),
                    '{{nom}}'             => (string)($c['last_name'] ?? ''),
                    '{{licence}}'         => (string)($c['licence'] ?? ''),
                    '{{date_fin}}'        => (string)($c['end_date'] ?? ''),
                    '{{contract_number}}' => (string)($c['contract_number'] ?? ''),
                    '{{email}}'           => (string)($u->user_email ?? ''),
                ];
                $subject = strtr($subj_tpl, $vars);
                $message = strtr($body_tpl, $vars);
                $to = locarc_parse_email_list(get_option('locarc_reminder_to', '{{email}}'), $vars);
                if (empty($to)) $to = [$u->user_email];
                $headers = ['Content-Type: text/html; charset=UTF-8'];
                $from = sanitize_email((string)get_option('locarc_reminder_from', get_option('admin_email')));
                if ($from !== '') {
                    $headers[] = 'From: ' . $from;
                    $headers[] = 'Reply-To: ' . $from;
                }
                $cc = locarc_parse_email_list(get_option('locarc_reminder_cc', ''), $vars);
                if (!empty($cc)) $headers[] = 'Cc: ' . implode(', ', $cc);
                $bcc = locarc_parse_email_list(get_option('locarc_reminder_bcc', ''), $vars);
                if (!empty($bcc)) $headers[] = 'Bcc: ' . implode(', ', $bcc);
                wp_mail(implode(', ', $to), $subject, nl2br($message), $headers);
            }
        }
    }

    // 2) End-of-week admin summaries (Friday)
    $is_friday = (wp_date('N') === '5');
    if ($is_friday && ($enabled_unpaid || $enabled_admin_exp)) {
        $lines = [];
        $subject_bits = [];

        if ($enabled_unpaid) {
            $unpaid = $wpdb->get_results(
                "SELECT c.*, m.first_name, m.last_name
                 FROM {$t['contracts']} c
                 LEFT JOIN {$t['members']} m ON m.licence = c.licence
                 WHERE c.status='active' AND c.is_paid=0
                 ORDER BY c.end_date ASC",
                ARRAY_A
            );
            $subject_bits[] = 'non payés';
            $lines[] = "Contrats non payés :";
            if (!$unpaid) {
                $lines[] = "- Aucun ✅";
            } else {
                // Prime name cache before loop.
                locarc_prime_member_names(array_column($unpaid, 'licence'));
                foreach ($unpaid as $c) {
                    if (trim((string)($c['first_name'] ?? '')) === '' && trim((string)($c['last_name'] ?? '')) === '') {
                        [$fn, $ln] = locarc_member_names($c['licence']);
                        $c['first_name'] = $fn;
                        $c['last_name']  = $ln;
                    }
                    $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                    $lines[] = "- {$name} ({$c['licence']}) / {$c['contract_type']} / fin : {$c['end_date']}";
                }
            }
            $lines[] = "";
        }

        if ($enabled_admin_exp) {
            $d1 = wp_date('Y-m-d', strtotime('+7 days', $now->getTimestamp()));
            $d2 = wp_date('Y-m-d', strtotime('+14 days', $now->getTimestamp()));
            $exp = $wpdb->get_results($wpdb->prepare(
                "SELECT c.*, m.first_name, m.last_name
                 FROM {$t['contracts']} c
                 LEFT JOIN {$t['members']} m ON m.licence = c.licence
                 WHERE c.status='active' AND c.end_date BETWEEN %s AND %s
                 ORDER BY c.end_date ASC",
                $d1, $d2
            ), ARRAY_A);

            $subject_bits[] = 'échéances';
            $lines[] = "Contrats qui se terminent entre {$d1} et {$d2} :";
            if (!$exp) {
                $lines[] = "- Aucun ✅";
            } else {
                // Prime name cache before loop.
                locarc_prime_member_names(array_column($exp, 'licence'));
                foreach ($exp as $c) {
                    if (trim((string)($c['first_name'] ?? '')) === '' && trim((string)($c['last_name'] ?? '')) === '') {
                        [$fn, $ln] = locarc_member_names($c['licence']);
                        $c['first_name'] = $fn;
                        $c['last_name']  = $ln;
                    }
                    $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                    $lines[] = "- {$name} ({$c['licence']}) / {$c['contract_type']} / fin : {$c['end_date']} / payé : " . ($c['is_paid'] ? 'Oui' : 'Non');
                }
            }
            $lines[] = "";
        }

        $subject = "[Location d'Arc] Récap hebdo";
        if ($subject_bits) $subject .= ' (' . implode(' + ', $subject_bits) . ')';
        $message = implode("\n", $lines);
        if (trim($message) !== '') {
            wp_mail($admin_to, $subject, $message);
        }
    }
}
