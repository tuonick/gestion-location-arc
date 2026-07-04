<?php
/**
 * Frontend Dashboard functions.
 *
 * @package LocArc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Custom plugin tables are accessed through $wpdb; table names come from locarc_tables().
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Frontend dashboard shortcode [locarc_dashboard]
 * Requires manage_options capability.
 */

add_action( 'wp_enqueue_scripts', 'locarc_fe_register_assets' );
add_shortcode( 'locarc_dashboard', 'locarc_fe_shortcode' );

function locarc_fe_register_assets() {
	$css = LOCARC_PLUGIN_DIR . 'assets/frontend-dashboard.css';
	$js  = LOCARC_PLUGIN_DIR . 'assets/frontend-dashboard.js';
	wp_register_style( 'locarc-fe-dashboard', plugins_url( '../assets/frontend-dashboard.css', __FILE__ ), array(), file_exists( $css ) ? filemtime( $css ) : LOCARC_VERSION );
	wp_register_script( 'locarc-fe-dashboard', plugins_url( '../assets/frontend-dashboard.js', __FILE__ ), array(), '20260522-001', true );
}

function locarc_fe_shortcode() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '<p>Vous n\'avez pas les droits pour accéder à ce tableau de bord.</p>';
	}
	wp_enqueue_style( 'locarc-fe-dashboard' );
	wp_enqueue_script( 'locarc-fe-dashboard' );

	// Build contract types for JS.
	$ct_config = locarc_contract_types_active();
	$ct_js     = array();
	foreach ( $ct_config as $key => $row ) {
		$ct_js[] = array(
			'key'   => $key,
			'label' => $row['label'],
			'price' => floatval( $row['price'] ?? 0 ),
		);
	}

	wp_localize_script(
		'locarc-fe-dashboard',
		'LOCARC_FE',
		array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'locarc_nonce' ),
			'adminUrl'      => admin_url( 'admin.php?page=locarc' ),
			'contractTypes' => $ct_js,
			'modules'       => array(
				'sights'                => (bool) get_option( 'locarc_enable_sights', 0 ),
				'sightRequired'         => (bool) get_option( 'locarc_sight_required', 0 ),
				'stabilizations'        => (bool) get_option( 'locarc_enable_stabilizations', 0 ),
				'stabilizationRequired' => (bool) get_option( 'locarc_stabilization_required', 0 ),
				'initBows'              => (bool) get_option( 'locarc_enable_init_bows', 0 ),
			),
		)
	);

	ob_start();
	locarc_fe_root();
	return ob_get_clean();
}

/* ─── Root markup ───────────────────────────────────────────────── */

function locarc_fe_root() {
	?>
	<div class="locarc-fe" id="locarc-fe" role="application">

		<!-- Top bar -->
		<header class="locarc-fe__bar">
		<button class="locarc-fe__ham" id="locarc-fe-ham" aria-label="Menu" aria-expanded="false" aria-controls="locarc-fe-nav">
			<span></span><span></span><span></span>
		</button>
		<div class="locarc-fe__brand">
			<svg class="locarc-fe__logo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
			<span>Location&nbsp;d'Arc</span>
		</div>
		<span class="locarc-fe__cur" id="locarc-fe-cur">Contrats</span>
		<a class="locarc-fe__admin-link" href="<?php echo esc_url( admin_url( 'admin.php?page=locarc' ) ); ?>" title="Interface d'administration complète" target="_blank" rel="noopener">
			<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" width="18" height="18"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
		</a>
		</header>

		<!-- Backdrop (covers main only, not theme nav) -->
		<div class="locarc-fe__backdrop" id="locarc-fe-backdrop" aria-hidden="true"></div>

		<!-- Side nav -->
		<nav class="locarc-fe__nav" id="locarc-fe-nav" aria-label="Sections">
		<div class="locarc-fe__nav-inner">
			<button class="locarc-fe__nav-item is-active" data-section="contracts" data-label="Contrats">
			<?php locarc_fe_icon( 'contracts' ); ?><span>Contrats</span>
			</button>
			<button class="locarc-fe__nav-item" data-section="rented" data-label="Matériel loué">
			<?php locarc_fe_icon( 'rented' ); ?><span>Matériel loué</span>
			</button>
			<button class="locarc-fe__nav-item" data-section="branches" data-label="Inventaire branches">
			<?php locarc_fe_icon( 'branches' ); ?><span>Inventaire branches</span>
			</button>
			<button class="locarc-fe__nav-item" data-section="handles" data-label="Inventaire poignées">
			<?php locarc_fe_icon( 'handles' ); ?><span>Inventaire poignées</span>
			</button>
			<?php if ( get_option( 'locarc_enable_sights', 0 ) ) : ?>
			<button class="locarc-fe__nav-item" data-section="sights" data-label="Inventaire viseurs">
				<?php locarc_fe_icon( 'sights' ); ?><span>Inventaire viseurs</span>
			</button>
			<?php endif; ?>
			<?php if ( get_option( 'locarc_enable_stabilizations', 0 ) ) : ?>
			<button class="locarc-fe__nav-item" data-section="stabilizations" data-label="Inventaire stabilisations">
				<?php locarc_fe_icon( 'stabilizations' ); ?><span>Inventaire stabilisations</span>
			</button>
			<?php endif; ?>
			<?php if ( get_option( 'locarc_enable_init_bows', 0 ) ) : ?>
			<button class="locarc-fe__nav-item" data-section="init_bows" data-label="Arcs d'Initiation">
				<?php locarc_fe_icon( 'init_bows' ); ?><span>Arcs d'Initiation</span>
			</button>
			<?php endif; ?>
		</div>
		<div class="locarc-fe__nav-footer">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=locarc' ) ); ?>" class="locarc-fe__nav-admin" target="_blank" rel="noopener">
			↗ Interface complète
			</a>
		</div>
		</nav>

		<!-- Main content -->
		<main class="locarc-fe__main" id="locarc-fe-main">

		<!-- Toast container (viewport-fixed via CSS) -->
		<div class="locarc-fe__toasts" id="locarc-fe-toasts" aria-live="assertive" aria-atomic="true"></div>

		<!-- Contracts section -->
		<section class="locarc-fe__section is-active" id="locarc-fe-contracts" aria-label="Contrats">
			<?php locarc_fe_section_contracts(); ?>
		</section>

		<!-- Rented section -->
		<section class="locarc-fe__section" id="locarc-fe-rented" aria-label="Matériel loué">
			<?php locarc_fe_section_rented(); ?>
		</section>

		<!-- Branches inventory -->
		<section class="locarc-fe__section" id="locarc-fe-branches" aria-label="Inventaire branches">
			<?php locarc_fe_section_branches(); ?>
		</section>

		<!-- Handles inventory -->
		<section class="locarc-fe__section" id="locarc-fe-handles" aria-label="Inventaire poignées">
			<?php locarc_fe_section_handles(); ?>
		</section>

		<?php if ( get_option( 'locarc_enable_sights', 0 ) ) : ?>
		<section class="locarc-fe__section" id="locarc-fe-sights" aria-label="Inventaire viseurs">
			<?php locarc_fe_section_sights(); ?>
		</section>
		<?php endif; ?>

		<?php if ( get_option( 'locarc_enable_stabilizations', 0 ) ) : ?>
		<section class="locarc-fe__section" id="locarc-fe-stabilizations" aria-label="Inventaire stabilisations">
			<?php locarc_fe_section_stabilizations(); ?>
		</section>
		<?php endif; ?>

		<?php if ( get_option( 'locarc_enable_init_bows', 0 ) ) : ?>
		<section class="locarc-fe__section" id="locarc-fe-init_bows" aria-label="Arcs d'Initiation">
			<?php locarc_fe_section_init_bows(); ?>
		</section>
		<?php endif; ?>

		</main>

		<!-- Slide-in drawer (shared for all edit modals) -->
		<div class="locarc-fe__drawer" id="locarc-fe-drawer" role="dialog" aria-modal="true" aria-labelledby="locarc-fe-drawer-title">
		<div class="locarc-fe__drawer-inner">
			<div class="locarc-fe__drawer-head">
			<h3 class="locarc-fe__drawer-title" id="locarc-fe-drawer-title">Modifier</h3>
			<button class="locarc-fe__drawer-close" id="locarc-fe-drawer-close" aria-label="Fermer le panneau">✕</button>
			</div>
			<div class="locarc-fe__drawer-body" id="locarc-fe-drawer-body">
			<!-- Populated dynamically by JS -->
			</div>
			<div class="locarc-fe__drawer-foot">
			<button class="locarc-fe__btn" id="locarc-fe-drawer-submit">Enregistrer</button>
			<button class="locarc-fe__btn locarc-fe__btn--outline" id="locarc-fe-drawer-cancel">Annuler</button>
			</div>
		</div>
		</div>

		<!-- Lightweight view-item modal (read-only, for clicking identifiers) -->
		<div class="locarc-fe__view-modal" id="locarc-fe-view-modal" role="dialog" aria-modal="true" aria-label="Détail du matériel">
		<div class="locarc-fe__view-card">
			<div class="locarc-fe__view-card-head">
			<h3 class="locarc-fe__view-card-title" id="locarc-fe-view-title">Matériel</h3>
			<button class="locarc-fe__view-card-close" id="locarc-fe-view-close" aria-label="Fermer">✕</button>
			</div>
			<div class="locarc-fe__view-card-body" id="locarc-fe-view-body">
			<!-- Populated by JS -->
			</div>
		</div>
		</div>

	</div>
	<?php
}

/* ─── Section: Contracts ────────────────────────────────────────── */

function locarc_fe_section_contracts() {
	global $wpdb;
	$t = locarc_tables();

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT c.*, m.first_name, m.last_name
             FROM %i c
             LEFT JOIN %i m ON m.licence = c.licence
             WHERE c.status='active'
             ORDER BY c.end_date ASC",
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);

	$archived = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT c.*, m.first_name, m.last_name
             FROM %i c
             LEFT JOIN %i m ON m.licence = c.licence
             WHERE c.status='archived'
             ORDER BY c.end_date DESC
             LIMIT 50",
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);

	$missing = array();
	foreach ( array_merge( $rows, $archived ) as $r ) {
		if ( trim( (string) ( $r['first_name'] ?? '' ) ) === '' && trim( (string) ( $r['last_name'] ?? '' ) ) === '' ) {
			$missing[] = $r['licence'];
		}
	}
	if ( ! empty( $missing ) ) {
		locarc_prime_member_names( $missing );
	}

	$total_active = count( $rows );
	$unpaid       = 0;
	$expiring     = 0;
	$today        = strtotime( wp_date( 'Y-m-d' ) );
	$three_weeks  = 21 * 86400;
	foreach ( $rows as $r ) {
		if ( ! intval( $r['is_paid'] ?? 0 ) && ( $r['contract_type'] ?? '' ) !== 'pret' ) {
			++$unpaid;
		}
		$end_ts = strtotime( $r['end_date'] ?? '' );
		if ( $end_ts && ( $end_ts - $today ) >= 0 && ( $end_ts - $today ) <= $three_weeks ) {
			++$expiring;
		}
	}
	?>
	<div class="locarc-fe__section-header">
		<h2 class="locarc-fe__section-title"><?php locarc_fe_icon( 'contracts' ); ?> Contrats</h2>
		<div class="locarc-fe__stats">
		<div class="locarc-fe__stat">
			<span class="locarc-fe__stat-val"><?php echo intval( $total_active ); ?></span>
			<span class="locarc-fe__stat-lbl">actifs</span>
		</div>
		<div class="locarc-fe__stat locarc-fe__stat--warn">
			<span class="locarc-fe__stat-val"><?php echo intval( $unpaid ); ?></span>
			<span class="locarc-fe__stat-lbl">non payés</span>
		</div>
		<div class="locarc-fe__stat locarc-fe__stat--info">
			<span class="locarc-fe__stat-val"><?php echo intval( $expiring ); ?></span>
			<span class="locarc-fe__stat-lbl">expirent bientôt</span>
		</div>
		</div>
	</div>

	<div class="locarc-fe__toolbar">
		<button class="locarc-fe__btn js-new-contract">+ Nouveau contrat</button>
		<input type="search" class="locarc-fe__search" placeholder="Rechercher…" data-target="contracts-table" aria-label="Filtrer les contrats">
		<?php
		$contract_type_options = locarc_contract_types_config();
		echo '<select class="locarc-fe__filter" data-filter-table="contracts-table" data-filter-col="ct" aria-label="Filtrer par type">';
		echo '<option value="">Tous types</option>';
		foreach ( $contract_type_options as $k => $row ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $row['label'] ) . '</option>';
		}
		echo '</select>';
		?>
		<select class="locarc-fe__filter" data-filter-table="contracts-table" data-filter-col="paid" aria-label="Filtrer par statut paiement">
		<option value="">Tous (payé)</option>
		<option value="1">Payé</option>
		<option value="0">Non payé</option>
		</select>
		<label class="locarc-fe__toggle-archived">
		<input type="checkbox" id="fe-contracts-show-archived"> Archivés
		</label>
	</div>

	<div class="locarc-fe__table-wrap">
		<table class="locarc-fe__table" id="fe-contracts-table">
		<thead>
			<tr>
			<th data-sort="none" style="width:36px;text-align:center;">#</th>
			<th data-sort="text">Licence</th>
			<th data-sort="text">Nom</th>
			<th data-sort="text">Type</th>
			<th data-sort="date">Fin</th>
			<th data-sort="text">Payé</th>
			<th data-sort="none">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php
			$row_num = 0;
			foreach ( $rows as $r ) :
				++$row_num;
				?>
				<?php
				if ( trim( (string) ( $r['first_name'] ?? '' ) ) === '' && trim( (string) ( $r['last_name'] ?? '' ) ) === '' ) {
					[$fn, $ln]       = locarc_member_names( $r['licence'] );
					$r['first_name'] = $fn;
					$r['last_name']  = $ln;
				}
				$full_name   = trim( ( $r['first_name'] ?? '' ) . ' ' . ( $r['last_name'] ?? '' ) );
				$end_ts      = strtotime( $r['end_date'] ?? '' );
				$is_expiring = ( $end_ts && ( $end_ts - $today ) >= 0 && ( $end_ts - $today ) <= $three_weeks );
				$is_expired  = ( $end_ts && $end_ts < $today );
				$is_pret     = ( $r['contract_type'] ?? '' ) === 'pret';
				$pdf_url     = ! empty( $r['pdf_path'] ) ? locarc_get_contract_pdf_url( $r ) : null;
				$row_cls     = '';
				if ( $is_expiring ) {
					$row_cls = 'is-expiring';
				} elseif ( $is_expired ) {
					$row_cls = 'is-expired';
				}
				?>
			<tr class="locarc-fe__row <?php echo esc_attr( $row_cls ); ?>"
				data-id="<?php echo intval( $r['id'] ); ?>"
				data-status="active"
				data-ct="<?php echo esc_attr( $r['contract_type'] ); ?>"
				data-paid="<?php echo intval( $r['is_paid'] ?? 0 ); ?>"
				data-search="<?php echo esc_attr( mb_strtolower( $r['licence'] . ' ' . $full_name . ' ' . locarc_contract_type_label( $r['contract_type'] ), 'UTF-8' ) ); ?>">
				<td style="text-align:center;color:var(--fe-muted,#646970);font-size:12px;"><?php echo esc_html( $row_num ); ?></td>
				<td><code><?php echo esc_html( $r['licence'] ); ?></code></td>
				<td><?php echo esc_html( $full_name ? $full_name : $r['licence'] ); ?></td>
				<td><?php echo esc_html( locarc_contract_type_label( $r['contract_type'] ) ); ?></td>
				<td class="<?php echo esc_attr( $is_expiring ? 'locarc-fe__cell--warn' : ( $is_expired ? 'locarc-fe__cell--danger' : '' ) ); ?>">
				<?php echo esc_html( locarc_fe_date( $r['end_date'] ) ); ?>
				</td>
				<td>
				<?php if ( $is_pret ) : ?>
					<span class="locarc-fe__badge locarc-fe__badge--neutral">—</span>
				<?php else : ?>
					<button class="locarc-fe__badge js-paid <?php echo intval( $r['is_paid'] ) ? 'locarc-fe__badge--ok' : 'locarc-fe__badge--warn'; ?>"
					data-id="<?php echo intval( $r['id'] ); ?>" data-paid="<?php echo intval( $r['is_paid'] ); ?>"
					title="Clic pour basculer">
					<?php echo intval( $r['is_paid'] ) ? 'Payé' : 'Non payé'; ?>
					</button>
				<?php endif; ?>
				</td>
				<td class="locarc-fe__actions">
				<div class="locarc-fe__dropdown">
					<button type="button" class="locarc-fe__btn locarc-fe__btn--sm locarc-fe__btn--outline locarc-fe__dropdown-trigger" data-id="<?php echo intval( $r['id'] ); ?>">Actions</button>
					<div class="locarc-fe__dropdown-menu">
					<?php if ( $pdf_url ) : ?>
						<a class="locarc-fe__dropdown-item locarc-fe__dropdown-item--pdf" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener">↓ Télécharger PDF</a>
						<div class="locarc-fe__dropdown-sep"></div>
					<?php endif; ?>
					<button type="button" class="locarc-fe__dropdown-item js-genpdf" data-id="<?php echo intval( $r['id'] ); ?>">↺ <?php echo $pdf_url ? 'Regénérer PDF' : 'Générer PDF'; ?></button>
					<button type="button" class="locarc-fe__dropdown-item js-sendmail" data-id="<?php echo intval( $r['id'] ); ?>">✉ Envoyer par email</button>
					<div class="locarc-fe__dropdown-sep"></div>
					<button type="button" class="locarc-fe__dropdown-item js-edit-contract" data-id="<?php echo intval( $r['id'] ); ?>">✎ Modifier</button>
					<button type="button" class="locarc-fe__dropdown-item js-renew-contract" data-id="<?php echo intval( $r['id'] ); ?>">⟳ Renouveler</button>
					<div class="locarc-fe__dropdown-sep"></div>
					<button type="button" class="locarc-fe__dropdown-item locarc-fe__dropdown-item--danger js-archive" data-id="<?php echo intval( $r['id'] ); ?>">Archiver</button>
					</div>
				</div>
				</td>
			</tr>
						<?php endforeach; ?>

			<?php foreach ( $archived as $r ) : ?>
				<?php
				if ( trim( (string) ( $r['first_name'] ?? '' ) ) === '' && trim( (string) ( $r['last_name'] ?? '' ) ) === '' ) {
					[$fn, $ln]       = locarc_member_names( $r['licence'] );
					$r['first_name'] = $fn;
					$r['last_name']  = $ln;
				}
				$full_name = trim( ( $r['first_name'] ?? '' ) . ' ' . ( $r['last_name'] ?? '' ) );
				$pdf_url   = ! empty( $r['pdf_path'] ) ? locarc_get_contract_pdf_url( $r ) : null;
				?>
			<tr class="locarc-fe__row locarc-fe__row--archived"
				data-id="<?php echo intval( $r['id'] ); ?>"
				data-status="archived"
				data-search="<?php echo esc_attr( mb_strtolower( $r['licence'] . ' ' . $full_name . ' ' . locarc_contract_type_label( $r['contract_type'] ), 'UTF-8' ) ); ?>"
				style="display:none">
				<td><code><?php echo esc_html( $r['licence'] ); ?></code></td>
				<td><?php echo esc_html( $full_name ? $full_name : $r['licence'] ); ?></td>
				<td><?php echo esc_html( locarc_contract_type_label( $r['contract_type'] ) ); ?></td>
				<td><?php echo esc_html( locarc_fe_date( $r['end_date'] ) ); ?></td>
				<td><span class="locarc-fe__badge locarc-fe__badge--neutral">—</span></td>
				<td>
				<?php if ( $pdf_url ) : ?>
					<a class="locarc-fe__btn locarc-fe__btn--sm" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener">PDF ↓</a>
				<?php else : ?>
					<span class="locarc-fe__muted">—</span>
				<?php endif; ?>
				</td>
				<td class="locarc-fe__actions">
				<button type="button" class="locarc-fe__btn locarc-fe__btn--sm locarc-fe__btn--outline js-restore" data-id="<?php echo intval( $r['id'] ); ?>">Restaurer</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
		</table>
	</div>
	<?php
}

/* ─── Section: Matériel loué ────────────────────────────────────── */

function locarc_fe_section_rented() {
	global $wpdb;
	$t = locarc_tables();

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT c.*, m.first_name, m.last_name
             FROM %i c
             LEFT JOIN %i m ON m.licence = c.licence
             WHERE c.status='active'
             ORDER BY m.last_name ASC, m.first_name ASC",
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);

	$missing = array();
	foreach ( $rows as $r ) {
		if ( trim( (string) ( $r['first_name'] ?? '' ) ) === '' && trim( (string) ( $r['last_name'] ?? '' ) ) === '' ) {
			$missing[] = $r['licence'];
		}
	}
	if ( ! empty( $missing ) ) {
		locarc_prime_member_names( $missing );
	}

	$with_both = 0;
	foreach ( $rows as $r ) {
		if ( ! empty( $r['handle_identifier'] ) && ! empty( $r['branches_identifier'] ) ) {
			++$with_both;
		}
	}
	?>
	<div class="locarc-fe__section-header">
		<h2 class="locarc-fe__section-title"><?php locarc_fe_icon( 'rented' ); ?> Matériel loué</h2>
		<div class="locarc-fe__stats">
		<div class="locarc-fe__stat">
			<span class="locarc-fe__stat-val"><?php echo intval( count( $rows ) ); ?></span>
			<span class="locarc-fe__stat-lbl">loueurs actifs</span>
		</div>
		<div class="locarc-fe__stat locarc-fe__stat--ok">
			<span class="locarc-fe__stat-val"><?php echo intval( $with_both ); ?></span>
			<span class="locarc-fe__stat-lbl">équipement complet</span>
		</div>
		<div class="locarc-fe__stat locarc-fe__stat--warn">
			<span class="locarc-fe__stat-val"><?php echo intval( count( $rows ) - $with_both ); ?></span>
			<span class="locarc-fe__stat-lbl">incomplets</span>
		</div>
		</div>
	</div>

	<div class="locarc-fe__toolbar">
		<input type="search" class="locarc-fe__search" placeholder="Rechercher…" data-target="rented-table" aria-label="Filtrer le matériel loué">
		<?php
		$rented_types = array();
		foreach ( $rows as $r ) {
			$rented_types[ $r['contract_type'] ?? '' ] = locarc_contract_type_label( $r['contract_type'] ?? '' ); }
		echo '<select class="locarc-fe__filter" data-filter-table="rented-table" data-filter-col="ct" aria-label="Filtrer par type">';
		echo '<option value="">Tous types</option>';
		foreach ( $rented_types as $k => $lbl ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $lbl ) . '</option>';
		}
		echo '</select>';
		?>
	</div>

	<div class="locarc-fe__table-wrap">
		<table class="locarc-fe__table" id="fe-rented-table">
		<thead>
			<tr>
			<th data-sort="text">Nom</th>
			<th data-sort="text">Licence</th>
			<th data-sort="text">Type</th>
			<th data-sort="text">Poignée</th>
			<th data-sort="text">Branches</th>
			<th data-sort="date">Fin contrat</th>
			<th data-sort="none">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $r ) : ?>
				<?php
				if ( trim( (string) ( $r['first_name'] ?? '' ) ) === '' && trim( (string) ( $r['last_name'] ?? '' ) ) === '' ) {
					[$fn, $ln]       = locarc_member_names( $r['licence'] );
					$r['first_name'] = $fn;
					$r['last_name']  = $ln;
				}
				$full_name    = trim( ( $r['first_name'] ?? '' ) . ' ' . ( $r['last_name'] ?? '' ) );
				$has_handle   = ! empty( $r['handle_identifier'] );
				$has_branches = ! empty( $r['branches_identifier'] );
				?>
			<tr class="locarc-fe__row"
				data-id="<?php echo intval( $r['id'] ); ?>"
				data-ct="<?php echo esc_attr( $r['contract_type'] ?? '' ); ?>"
				data-search="<?php echo esc_attr( mb_strtolower( $r['licence'] . ' ' . $full_name . ' ' . ( $r['handle_identifier'] ?? '' ) . ' ' . ( $r['branches_identifier'] ?? '' ), 'UTF-8' ) ); ?>">
				<td><?php echo esc_html( $full_name ? $full_name : $r['licence'] ); ?></td>
				<td><code><?php echo esc_html( $r['licence'] ); ?></code></td>
				<td><?php echo esc_html( locarc_contract_type_label( $r['contract_type'] ?? '' ) ); ?></td>
				<td>
				<?php if ( $has_handle ) : ?>
					<button class="locarc-fe__badge locarc-fe__badge--ok js-view-item"
					data-identifier="<?php echo esc_attr( $r['handle_identifier'] ); ?>"
					data-kind="handles"
					title="Voir le détail de la poignée">
					<?php echo esc_html( $r['handle_identifier'] ); ?>
					</button>
				<?php else : ?>
					<span class="locarc-fe__badge locarc-fe__badge--neutral">—</span>
				<?php endif; ?>
				</td>
				<td>
				<?php if ( $has_branches ) : ?>
					<button class="locarc-fe__badge locarc-fe__badge--ok js-view-item"
					data-identifier="<?php echo esc_attr( $r['branches_identifier'] ); ?>"
					data-kind="branches"
					title="Voir le détail des branches">
					<?php echo esc_html( $r['branches_identifier'] ); ?>
					</button>
				<?php else : ?>
					<span class="locarc-fe__badge locarc-fe__badge--neutral">—</span>
				<?php endif; ?>
				</td>
				<td><?php echo esc_html( locarc_fe_date( $r['end_date'] ) ); ?></td>
				<td>
				<button class="locarc-fe__btn locarc-fe__btn--sm locarc-fe__btn--outline js-edit-assignment"
					data-id="<?php echo intval( $r['id'] ); ?>">Modifier affectation</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
		</table>
	</div>
	<?php
}

/* ─── Section: Branches inventory ──────────────────────────────── */

function locarc_fe_section_branches() {
	global $wpdb;
	$t = locarc_tables();

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT b.*, c.licence AS renter_licence, m.first_name AS renter_first, m.last_name AS renter_last
             FROM %i b
             LEFT JOIN %i c ON c.status='active' AND c.branches_identifier = b.identifier
             LEFT JOIN %i m ON m.licence = c.licence
             ORDER BY b.is_available DESC, CAST(b.size AS UNSIGNED) ASC, CAST(b.power AS DECIMAL(5,2)) ASC, b.identifier ASC",
			$t['branches'],
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);

	$total   = count( $rows );
	$dispo   = 0;
	$rented  = 0;
	$unavail = 0;
	foreach ( $rows as $r ) {
		$s = intval( $r['is_available'] ?? 0 );
		if ( 1 === $s ) {
			++$dispo;
		} elseif ( 0 === $s ) {
			++$rented;
		} else {
			++$unavail;
		}
	}
	?>
	<div class="locarc-fe__section-header">
		<h2 class="locarc-fe__section-title"><?php locarc_fe_icon( 'branches' ); ?> Inventaire branches</h2>
		<div class="locarc-fe__stats">
		<div class="locarc-fe__stat"><span class="locarc-fe__stat-val"><?php echo intval( $total ); ?></span><span class="locarc-fe__stat-lbl">total</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--ok"><span class="locarc-fe__stat-val"><?php echo intval( $dispo ); ?></span><span class="locarc-fe__stat-lbl">disponibles</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--warn"><span class="locarc-fe__stat-val"><?php echo intval( $rented ); ?></span><span class="locarc-fe__stat-lbl">loués</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--danger"><span class="locarc-fe__stat-val"><?php echo intval( $unavail ); ?></span><span class="locarc-fe__stat-lbl">indisponibles</span></div>
		</div>
	</div>

	<div class="locarc-fe__toolbar">
		<button class="locarc-fe__btn locarc-fe__btn--sm js-add-item" data-kind="branches">+ Ajouter</button>
		<input type="search" class="locarc-fe__search" placeholder="Rechercher…" data-target="branches-table" aria-label="Filtrer les branches">
		<select class="locarc-fe__filter" data-filter-table="branches-table" data-filter-col="avail" aria-label="Filtrer par disponibilité">
		<option value="">Toutes dispo</option>
		<option value="1">Disponible</option>
		<option value="0">Loué</option>
		<option value="2">FLAG</option>
		<option value="3">Obsolète</option>
		<option value="4">En réparation</option>
		<option value="5">H-S</option>
		</select>
	</div>

	<div class="locarc-fe__table-wrap">
		<table class="locarc-fe__table" id="fe-branches-table">
		<thead>
			<tr>
			<th data-sort="text">Identifiant</th>
			<th data-sort="text">Marque</th>
			<th data-sort="text">Modèle</th>
			<th data-sort="num">Taille</th>
			<th data-sort="num">Puissance</th>
			<th data-sort="text">Dispo</th>
			<th data-sort="text">Loueur</th>
			<th data-sort="none">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $r ) : ?>
				<?php
				$renter = '';
				if ( ! empty( $r['renter_licence'] ) ) {
					$renter = trim( ( $r['renter_first'] ?? '' ) . ' ' . ( $r['renter_last'] ?? '' ) );
					if ( '' === $renter ) {
						$renter = $r['renter_licence'];
					}
				}
				$avail_val   = intval( $r['is_available'] ?? 0 );
				$avail_map   = array(
					1 => 'ok',
					0 => 'warn',
					2 => 'info',
					3 => 'danger',
					4 => 'danger',
					5 => 'danger',
				);
				$avail_key   = $avail_map[ $avail_val ] ?? 'neutral';
				$avail_label = locarc_availability_label( $avail_val );
				?>
			<tr class="locarc-fe__row"
				data-id="<?php echo intval( $r['id'] ); ?>"
				data-avail="<?php echo intval( $avail_val ); ?>"
				data-search="<?php echo esc_attr( mb_strtolower( $r['identifier'] . ' ' . ( $r['brand'] ?? '' ) . ' ' . ( $r['model'] ?? '' ) . ' ' . $avail_label, 'UTF-8' ) ); ?>">
				<td><code><?php echo esc_html( $r['identifier'] ); ?></code></td>
				<td><?php echo esc_html( $r['brand'] ?? '' ); ?></td>
				<td><?php echo esc_html( $r['model'] ?? '' ); ?></td>
				<td><?php echo esc_html( $r['size'] ); ?></td>
				<td><?php echo esc_html( $r['power'] ); ?></td>
				<td><span class="locarc-fe__badge locarc-fe__badge--<?php echo esc_attr( $avail_key ); ?>"><?php echo esc_html( $avail_label ); ?></span></td>
				<td><?php echo esc_html( $renter ); ?></td>
				<td>
				<button class="locarc-fe__btn locarc-fe__btn--sm locarc-fe__btn--outline js-edit-item"
					data-id="<?php echo intval( $r['id'] ); ?>" data-kind="branches">Modifier</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
		</table>
	</div>
	<?php
}

/* ─── Section: Handles inventory ────────────────────────────────── */

function locarc_fe_section_handles() {
	global $wpdb;
	$t = locarc_tables();

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT h.*, c.licence AS renter_licence, m.first_name AS renter_first, m.last_name AS renter_last
             FROM %i h
             LEFT JOIN %i c ON c.status='active' AND c.handle_identifier = h.identifier
             LEFT JOIN %i m ON m.licence = c.licence
             ORDER BY h.is_available DESC, CAST(h.size AS UNSIGNED) ASC, h.handedness ASC, h.identifier ASC",
			$t['handles'],
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);

	$total   = count( $rows );
	$dispo_r = 0;
	$dispo_l = 0;
	$rented  = 0;
	foreach ( $rows as $r ) {
		$s = intval( $r['is_available'] ?? 0 );
		if ( 1 === $s ) {
			if ( trim( $r['handedness'] ?? '' ) === 'Gauche' ) {
				++$dispo_l;
			} else {
				++$dispo_r;
			}
		} elseif ( 0 === $s ) {
			++$rented;
		}
	}
	?>
	<div class="locarc-fe__section-header">
		<h2 class="locarc-fe__section-title"><?php locarc_fe_icon( 'handles' ); ?> Inventaire poignées</h2>
		<div class="locarc-fe__stats">
		<div class="locarc-fe__stat"><span class="locarc-fe__stat-val"><?php echo intval( $total ); ?></span><span class="locarc-fe__stat-lbl">total</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--ok"><span class="locarc-fe__stat-val"><?php echo intval( $dispo_r ); ?></span><span class="locarc-fe__stat-lbl">dispo droite</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--ok"><span class="locarc-fe__stat-val"><?php echo intval( $dispo_l ); ?></span><span class="locarc-fe__stat-lbl">dispo gauche</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--warn"><span class="locarc-fe__stat-val"><?php echo intval( $rented ); ?></span><span class="locarc-fe__stat-lbl">loués</span></div>
		</div>
	</div>

	<div class="locarc-fe__toolbar">
		<button class="locarc-fe__btn locarc-fe__btn--sm js-add-item" data-kind="handles">+ Ajouter</button>
		<input type="search" class="locarc-fe__search" placeholder="Rechercher…" data-target="handles-table" aria-label="Filtrer les poignées">
		<select class="locarc-fe__filter" data-filter-table="handles-table" data-filter-col="avail" aria-label="Filtrer par disponibilité">
		<option value="">Toutes dispo</option>
		<option value="1">Disponible</option>
		<option value="0">Loué</option>
		<option value="2">FLAG</option>
		<option value="3">Obsolète</option>
		<option value="4">En réparation</option>
		<option value="5">H-S</option>
		</select>
		<select class="locarc-fe__filter" data-filter-table="handles-table" data-filter-col="handed" aria-label="Filtrer par latéralité">
		<option value="">Toutes latéralités</option>
		<option value="Droite">Droite</option>
		<option value="Gauche">Gauche</option>
		</select>
	</div>

	<div class="locarc-fe__table-wrap">
		<table class="locarc-fe__table" id="fe-handles-table">
		<thead>
			<tr>
			<th data-sort="text">Identifiant</th>
			<th data-sort="text">Marque</th>
			<th data-sort="text">Modèle</th>
			<th data-sort="num">Taille</th>
			<th data-sort="text">Latéralité</th>
			<th data-sort="text">Dispo</th>
			<th data-sort="text">Loueur</th>
			<th data-sort="none">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $r ) : ?>
				<?php
				$renter = '';
				if ( ! empty( $r['renter_licence'] ) ) {
					$renter = trim( ( $r['renter_first'] ?? '' ) . ' ' . ( $r['renter_last'] ?? '' ) );
					if ( '' === $renter ) {
						$renter = $r['renter_licence'];
					}
				}
				$avail_val   = intval( $r['is_available'] ?? 0 );
				$avail_map   = array(
					1 => 'ok',
					0 => 'warn',
					2 => 'info',
					3 => 'danger',
					4 => 'danger',
					5 => 'danger',
				);
				$avail_key   = $avail_map[ $avail_val ] ?? 'neutral';
				$avail_label = locarc_availability_label( $avail_val );
				?>
			<tr class="locarc-fe__row"
				data-id="<?php echo intval( $r['id'] ); ?>"
				data-avail="<?php echo intval( $avail_val ); ?>"
				data-handed="<?php echo esc_attr( $r['handedness'] ?? '' ); ?>"
				data-search="<?php echo esc_attr( mb_strtolower( $r['identifier'] . ' ' . ( $r['brand'] ?? '' ) . ' ' . ( $r['model'] ?? '' ) . ' ' . ( $r['handedness'] ?? '' ) . ' ' . $avail_label, 'UTF-8' ) ); ?>">
				<td><code><?php echo esc_html( $r['identifier'] ); ?></code></td>
				<td><?php echo esc_html( $r['brand'] ?? '' ); ?></td>
				<td><?php echo esc_html( $r['model'] ?? '' ); ?></td>
				<td><?php echo esc_html( $r['size'] ); ?></td>
				<td><?php echo esc_html( $r['handedness'] ?? '' ); ?></td>
				<td><span class="locarc-fe__badge locarc-fe__badge--<?php echo esc_attr( $avail_key ); ?>"><?php echo esc_html( $avail_label ); ?></span></td>
				<td><?php echo esc_html( $renter ); ?></td>
				<td>
				<button class="locarc-fe__btn locarc-fe__btn--sm locarc-fe__btn--outline js-edit-item"
					data-id="<?php echo intval( $r['id'] ); ?>" data-kind="handles">Modifier</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
		</table>
	</div>
	<?php
}

/* ─── Section: Sights inventory ─────────────────────────────────── */

function locarc_fe_section_sights() {
	global $wpdb;
	$t       = locarc_tables();
	$rows    = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT s.*, c.licence AS renter_licence, m.first_name AS renter_first, m.last_name AS renter_last
             FROM %i s
             LEFT JOIN %i c ON c.status='active' AND c.sight_identifier = s.identifier
             LEFT JOIN %i m ON m.licence = c.licence
             ORDER BY s.is_available DESC, s.identifier ASC",
			$t['sights'],
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);
	$total   = count( $rows );
	$dispo   = 0;
	$rented  = 0;
	$unavail = 0;
	foreach ( $rows as $r ) {
		$s = intval( $r['is_available'] ?? 0 );
		if ( 1 === $s ) {
			++$dispo;
		} elseif ( 0 === $s ) {
			++$rented;
		} else {
			++$unavail;
		}
	}
	?>
	<div class="locarc-fe__section-header">
		<h2 class="locarc-fe__section-title"><?php locarc_fe_icon( 'sights' ); ?> Inventaire viseurs</h2>
		<div class="locarc-fe__stats">
		<div class="locarc-fe__stat"><span class="locarc-fe__stat-val"><?php echo intval( $total ); ?></span><span class="locarc-fe__stat-lbl">total</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--ok"><span class="locarc-fe__stat-val"><?php echo intval( $dispo ); ?></span><span class="locarc-fe__stat-lbl">disponibles</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--warn"><span class="locarc-fe__stat-val"><?php echo intval( $rented ); ?></span><span class="locarc-fe__stat-lbl">loués</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--danger"><span class="locarc-fe__stat-val"><?php echo intval( $unavail ); ?></span><span class="locarc-fe__stat-lbl">indisponibles</span></div>
		</div>
	</div>
	<div class="locarc-fe__toolbar">
		<button class="locarc-fe__btn locarc-fe__btn--sm js-add-item" data-kind="sights">+ Ajouter</button>
		<input type="search" class="locarc-fe__search" placeholder="Rechercher…" data-target="sights-table" aria-label="Filtrer les viseurs">
		<select class="locarc-fe__filter" data-filter-table="sights-table" data-filter-col="avail" aria-label="Filtrer par disponibilité">
		<option value="">Toutes dispo</option>
		<option value="1">Disponible</option>
		<option value="0">Loué</option>
		<option value="2">FLAG</option>
		<option value="3">Obsolète</option>
		<option value="4">En réparation</option>
		<option value="5">H-S</option>
		</select>
	</div>
	<div class="locarc-fe__table-wrap">
		<table class="locarc-fe__table" id="fe-sights-table">
		<thead><tr>
			<th data-sort="text">Identifiant</th>
			<th data-sort="text">Marque</th>
			<th data-sort="text">Modèle</th>
			<th data-sort="text">Latéralité</th>
			<th data-sort="text">Dispo</th>
			<th data-sort="text">Loueur</th>
			<th data-sort="none">Actions</th>
		</tr></thead>
		<tbody>
			<?php foreach ( $rows as $r ) : ?>
				<?php
				$renter = '';
				if ( ! empty( $r['renter_licence'] ) ) {
					$renter = trim( ( $r['renter_first'] ?? '' ) . ' ' . ( $r['renter_last'] ?? '' ) );
					if ( '' === $renter ) {
						$renter = $r['renter_licence'];
					}
				}
				$avail_val   = intval( $r['is_available'] ?? 0 );
				$avail_map   = array(
					1 => 'ok',
					0 => 'warn',
					2 => 'info',
					3 => 'danger',
					4 => 'danger',
					5 => 'danger',
				);
				$avail_key   = $avail_map[ $avail_val ] ?? 'neutral';
				$avail_label = locarc_availability_label( $avail_val );
				?>
			<tr class="locarc-fe__row"
				data-id="<?php echo intval( $r['id'] ); ?>"
				data-avail="<?php echo intval( $avail_val ); ?>"
				data-search="<?php echo esc_attr( mb_strtolower( $r['identifier'] . ' ' . $r['brand'] . ' ' . $r['model'] . ' ' . $avail_label, 'UTF-8' ) ); ?>">
				<td><code><?php echo esc_html( $r['identifier'] ); ?></code></td>
				<td><?php echo esc_html( $r['brand'] ); ?></td>
				<td><?php echo esc_html( $r['model'] ); ?></td>
				<td><?php echo esc_html( $r['handedness'] ); ?></td>
				<td><span class="locarc-fe__badge locarc-fe__badge--<?php echo esc_attr( $avail_key ); ?>"><?php echo esc_html( $avail_label ); ?></span></td>
				<td><?php echo esc_html( $renter ); ?></td>
				<td>
				<button class="locarc-fe__btn locarc-fe__btn--sm locarc-fe__btn--outline js-edit-item"
					data-id="<?php echo intval( $r['id'] ); ?>" data-kind="sights">Modifier</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
		</table>
	</div>
	<?php
}

/* ─── Section: Init bows inventory ──────────────────────────────── */

function locarc_fe_section_stabilizations() {
	global $wpdb;
	$t       = locarc_tables();
	$rows    = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT s.*, c.licence AS renter_licence, m.first_name AS renter_first, m.last_name AS renter_last
             FROM %i s
             LEFT JOIN %i c ON c.status='active' AND c.stabilization_identifier = s.identifier
             LEFT JOIN %i m ON m.licence = c.licence
             ORDER BY s.is_available DESC, s.identifier ASC",
			$t['stabilizations'],
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);
	$total   = count( $rows );
	$dispo   = 0;
	$rented  = 0;
	$unavail = 0;
	foreach ( $rows as $r ) {
		$s = intval( $r['is_available'] ?? 0 );
		if ( 1 === $s ) {
			++$dispo;
		} elseif ( 0 === $s ) {
			++$rented;
		} else {
			++$unavail;
		}
	}
	?>
	<div class="locarc-fe__section-header">
		<h2 class="locarc-fe__section-title"><?php locarc_fe_icon( 'stabilizations' ); ?> Inventaire stabilisations</h2>
		<div class="locarc-fe__stats">
		<div class="locarc-fe__stat"><span class="locarc-fe__stat-val"><?php echo intval( $total ); ?></span><span class="locarc-fe__stat-lbl">total</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--ok"><span class="locarc-fe__stat-val"><?php echo intval( $dispo ); ?></span><span class="locarc-fe__stat-lbl">disponibles</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--warn"><span class="locarc-fe__stat-val"><?php echo intval( $rented ); ?></span><span class="locarc-fe__stat-lbl">louees</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--danger"><span class="locarc-fe__stat-val"><?php echo intval( $unavail ); ?></span><span class="locarc-fe__stat-lbl">indisponibles</span></div>
		</div>
	</div>
	<div class="locarc-fe__toolbar">
		<button class="locarc-fe__btn locarc-fe__btn--sm js-add-item" data-kind="stabilizations">+ Ajouter</button>
		<input type="search" class="locarc-fe__search" placeholder="Rechercher..." data-target="stabilizations-table" aria-label="Filtrer les stabilisations">
		<select class="locarc-fe__filter" data-filter-table="stabilizations-table" data-filter-col="avail" aria-label="Filtrer par disponibilite">
		<option value="">Tous etats</option>
		<option value="1">Disponible</option>
		<option value="0">Louee</option>
		<option value="2">FLAG</option>
		<option value="3">Obsolete</option>
		<option value="4">En reparation</option>
		<option value="5">H-S</option>
		</select>
	</div>
	<div class="locarc-fe__table-wrap">
		<table class="locarc-fe__table" id="fe-stabilizations-table">
		<thead><tr>
			<th data-sort="text">Identifiant</th>
			<th data-sort="text">Marque</th>
			<th data-sort="text">Mod&egrave;le</th>
			<th data-sort="text">&Eacute;tat</th>
			<th data-sort="text">Commentaire</th>
			<th data-sort="text">Loueur</th>
			<th data-sort="none">Actions</th>
		</tr></thead>
		<tbody>
			<?php foreach ( $rows as $r ) : ?>
				<?php
				$renter = '';
				if ( ! empty( $r['renter_licence'] ) ) {
					$renter = trim( ( $r['renter_first'] ?? '' ) . ' ' . ( $r['renter_last'] ?? '' ) );
					if ( '' === $renter ) {
						$renter = $r['renter_licence'];
					}
				}
				$avail_val   = intval( $r['is_available'] ?? 0 );
				$avail_map   = array(
					1 => 'ok',
					0 => 'warn',
					2 => 'info',
					3 => 'danger',
					4 => 'danger',
					5 => 'danger',
				);
				$avail_key   = $avail_map[ $avail_val ] ?? 'neutral';
				$avail_label = locarc_availability_label( $avail_val );
				?>
			<tr class="locarc-fe__row"
				data-id="<?php echo intval( $r['id'] ); ?>"
				data-avail="<?php echo intval( $avail_val ); ?>"
				data-search="<?php echo esc_attr( mb_strtolower( $r['identifier'] . ' ' . $r['brand'] . ' ' . $r['model'] . ' ' . ( $r['comment'] ?? '' ) . ' ' . $avail_label, 'UTF-8' ) ); ?>">
				<td><code><?php echo esc_html( $r['identifier'] ); ?></code></td>
				<td><?php echo esc_html( $r['brand'] ); ?></td>
				<td><?php echo esc_html( $r['model'] ); ?></td>
				<td><span class="locarc-fe__badge locarc-fe__badge--<?php echo esc_attr( $avail_key ); ?>"><?php echo esc_html( $avail_label ); ?></span></td>
				<td><?php echo esc_html( $r['comment'] ?? '' ); ?></td>
				<td><?php echo esc_html( $renter ); ?></td>
				<td>
				<button class="locarc-fe__btn locarc-fe__btn--sm locarc-fe__btn--outline js-edit-item"
					data-id="<?php echo intval( $r['id'] ); ?>" data-kind="stabilizations">Modifier</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
		</table>
	</div>
	<?php
}

function locarc_fe_section_init_bows() {
	global $wpdb;
	$t       = locarc_tables();
	$rows    = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ib.*, c.licence AS renter_licence, m.first_name AS renter_first, m.last_name AS renter_last
             FROM %i ib
             LEFT JOIN %i c ON c.status='active' AND c.init_bow_identifier = ib.identifier
             LEFT JOIN %i m ON m.licence = c.licence
             ORDER BY ib.is_available DESC, CAST(ib.size AS UNSIGNED) ASC, CAST(ib.power AS DECIMAL(5,2)) ASC, ib.identifier ASC",
			$t['init_bows'],
			$t['contracts'],
			$t['members']
		),
		ARRAY_A
	);
	$total   = count( $rows );
	$dispo   = 0;
	$rented  = 0;
	$unavail = 0;
	foreach ( $rows as $r ) {
		$s = intval( $r['is_available'] ?? 0 );
		if ( 1 === $s ) {
			++$dispo;
		} elseif ( 0 === $s ) {
			++$rented;
		} else {
			++$unavail;
		}
	}
	?>
	<div class="locarc-fe__section-header">
		<h2 class="locarc-fe__section-title"><?php locarc_fe_icon( 'init_bows' ); ?> Arcs d'Initiation</h2>
		<div class="locarc-fe__stats">
		<div class="locarc-fe__stat"><span class="locarc-fe__stat-val"><?php echo intval( $total ); ?></span><span class="locarc-fe__stat-lbl">total</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--ok"><span class="locarc-fe__stat-val"><?php echo intval( $dispo ); ?></span><span class="locarc-fe__stat-lbl">disponibles</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--warn"><span class="locarc-fe__stat-val"><?php echo intval( $rented ); ?></span><span class="locarc-fe__stat-lbl">loués</span></div>
		<div class="locarc-fe__stat locarc-fe__stat--danger"><span class="locarc-fe__stat-val"><?php echo intval( $unavail ); ?></span><span class="locarc-fe__stat-lbl">indisponibles</span></div>
		</div>
	</div>
	<div class="locarc-fe__toolbar">
		<button class="locarc-fe__btn locarc-fe__btn--sm js-add-item" data-kind="init_bows">+ Ajouter</button>
		<input type="search" class="locarc-fe__search" placeholder="Rechercher…" data-target="init_bows-table" aria-label="Filtrer les arcs d'initiation">
		<select class="locarc-fe__filter" data-filter-table="init_bows-table" data-filter-col="avail" aria-label="Filtrer par disponibilité">
		<option value="">Toutes dispo</option>
		<option value="1">Disponible</option>
		<option value="0">Loué</option>
		<option value="2">FLAG</option>
		<option value="3">Obsolète</option>
		<option value="4">En réparation</option>
		<option value="5">H-S</option>
		</select>
	</div>
	<div class="locarc-fe__table-wrap">
		<table class="locarc-fe__table" id="fe-init_bows-table">
		<thead><tr>
			<th data-sort="text">Identifiant</th>
			<th data-sort="text">Poign&eacute;e</th>
			<th data-sort="text">Branches</th>
			<th data-sort="num">Taille</th>
			<th data-sort="num">Puissance</th>
			<th data-sort="text">Lat&eacute;ralit&eacute;</th>
			<th data-sort="text">Dispo</th>
			<th data-sort="text">Loueur</th>
			<th data-sort="none">Actions</th>
		</tr></thead>
		<tbody>
			<?php foreach ( $rows as $r ) : ?>
				<?php
				$renter = '';
				if ( ! empty( $r['renter_licence'] ) ) {
					$renter = trim( ( $r['renter_first'] ?? '' ) . ' ' . ( $r['renter_last'] ?? '' ) );
					if ( '' === $renter ) {
						$renter = $r['renter_licence'];
					}
				}
				$avail_val   = intval( $r['is_available'] ?? 0 );
				$avail_map   = array(
					1 => 'ok',
					0 => 'warn',
					2 => 'info',
					3 => 'danger',
					4 => 'danger',
					5 => 'danger',
				);
				$avail_key   = $avail_map[ $avail_val ] ?? 'neutral';
				$avail_label = locarc_availability_label( $avail_val );
				?>
			<tr class="locarc-fe__row"
				data-id="<?php echo intval( $r['id'] ); ?>"
				data-avail="<?php echo intval( $avail_val ); ?>"
				data-search="<?php echo esc_attr( mb_strtolower( $r['identifier'] . ' ' . ( $r['brand'] ?? '' ) . ' ' . ( $r['model'] ?? '' ) . ' ' . $avail_label, 'UTF-8' ) ); ?>">
				<td><code><?php echo esc_html( $r['identifier'] ); ?></code></td>
				<td><?php echo esc_html( $r['brand'] ?? '' ); ?></td>
				<td><?php echo esc_html( $r['model'] ?? '' ); ?></td>
				<td><?php echo esc_html( $r['size'] ); ?></td>
				<td><?php echo esc_html( $r['power'] ); ?></td>
				<td><?php echo esc_html( $r['handedness'] ); ?></td>
				<td><span class="locarc-fe__badge locarc-fe__badge--<?php echo esc_attr( $avail_key ); ?>"><?php echo esc_html( $avail_label ); ?></span></td>
				<td><?php echo esc_html( $renter ); ?></td>
				<td>
				<button class="locarc-fe__btn locarc-fe__btn--sm locarc-fe__btn--outline js-edit-item"
					data-id="<?php echo intval( $r['id'] ); ?>" data-kind="init_bows">Modifier</button>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
		</table>
	</div>
	<?php
}

/* ─── Helpers ───────────────────────────────────────────────────── */

function locarc_fe_date( $mysql_date ) {
	if ( ! $mysql_date ) {
		return '—';
	}
	$ts = strtotime( $mysql_date );
	return $ts ? wp_date( 'd/m/Y', $ts ) : $mysql_date;
}

function locarc_fe_icon( $section ) {
	$icons       = array(
		'contracts'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
		'rented'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>',
		'branches'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>',
		'handles'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
		'sights'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="9"/><line x1="12" y1="3" x2="12" y2="1"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="3" y1="12" x2="1" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>',
		'stabilizations' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="3" x2="12" y2="21"/><line x1="5" y1="8" x2="19" y2="8"/><line x1="7" y1="16" x2="17" y2="16"/><circle cx="12" cy="12" r="2"/></svg>',
		'init_bows'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/><path d="M12 6v6l4 2"/></svg>',
	);
	$svg         = $icons[ $section ] ?? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>';
	$allowed_svg = array(
		'svg'      => array(
			'viewbox'         => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'aria-hidden'     => true,
			'width'           => true,
			'height'          => true,
		),
		'path'     => array(
			'd'         => true,
			'fill'      => true,
			'fill-rule' => true,
			'clip-rule' => true,
		),
		'polyline' => array( 'points' => true ),
		'line'     => array(
			'x1' => true,
			'y1' => true,
			'x2' => true,
			'y2' => true,
		),
		'circle'   => array(
			'cx' => true,
			'cy' => true,
			'r'  => true,
		),
		'rect'     => array(
			'x'      => true,
			'y'      => true,
			'width'  => true,
			'height' => true,
			'rx'     => true,
		),
	);
	echo wp_kses( $svg, $allowed_svg );
}
