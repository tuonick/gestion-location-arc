-- ============================================================
--  Stabilization inventory insertion - ACSIM
--  Adjust the "wp_" prefix if your WordPress installation uses another one.
-- ============================================================
--
--  Accessory allocation across the 44 stabilizations:
--
--    Dampers:
--      AIM8 55mm    : 20 × 18,40 €  =  368,00 €
--      AIM8 40mm M8 :  4 × 21,00 €  =   84,00 €
--      AIM M8 hist. :  6 × 15,00 €  =   90,00 €
--      Subtotal     :                   542,00 EUR
--
--    Weights and accessories:
--      LW M8 30g weight         : 30 x 11,40 EUR =  342,00 EUR
--      Intermediate D16 weight : 16 x  9,02 EUR =  144,32 EUR
--      LW weight kit           :  2 x 31,50 EUR =   63,00 EUR
--      D16 cap                 :  5 x  4,46 EUR =   22,30 EUR
--      Subtotal                :                571,62 EUR
--
--    Total accessories     : 1 113,62 EUR
--    Stabilization count   : 43
--    Allocation per item   : 1 113,62 / 43 = 25,90 EUR
--
--  Final price by type (stabilization + accessory allocation):
--    EVO 15 / XEVO 15 Recurve      : 229,00 + 25,90 = 254,90 EUR
--    Uni Vbar Carbone Standard     : 110,00 + 25,90 = 135,90 EUR
--    Central Seul Carbone Standard :  45,00 + 25,90 =  70,90 EUR
-- ============================================================

INSERT INTO `wp_locarc_stabilizations`
    (`identifier`, `brand`, `model`, `purchase_year`, `purchase_price`, `is_available`)
VALUES

-- EVO 15 / XEVO 15 Recurve - 27 units
('EVO-01',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-02',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-03',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-04',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-05',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-06',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-07',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-08',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-09',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-10',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-11',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-12',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-13',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-14',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-15',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-16',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-17',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-18',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-19',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-20',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-21',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-22',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-23',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-24',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-25',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-26',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),
('EVO-27',  NULL, 'EVO 15 / XEVO 15 Recurve', NULL, 254.90, 1),

-- Uni Vbar Carbone Standard - 10 units (purchased in 2019)
('UVBAR-01', NULL, 'Uni Vbar Carbone Standard', 2019, 135.90, 1),
('UVBAR-02', NULL, 'Uni Vbar Carbone Standard', 2019, 135.90, 1),
('UVBAR-03', NULL, 'Uni Vbar Carbone Standard', 2019, 135.90, 1),
('UVBAR-04', NULL, 'Uni Vbar Carbone Standard', 2019, 135.90, 1),
('UVBAR-05', NULL, 'Uni Vbar Carbone Standard', 2019, 135.90, 1),
('UVBAR-06', NULL, 'Uni Vbar Carbone Standard', 2019, 135.90, 1),
('UVBAR-07', NULL, 'Uni Vbar Carbone Standard', 2019, 135.90, 1),
('UVBAR-08', NULL, 'Uni Vbar Carbone Standard', 2019, 135.90, 1),
('UVBAR-09', NULL, 'Uni Vbar Carbone Standard', 2019, 135.90, 1),
('UVBAR-10', NULL, 'Uni Vbar Carbone Standard', 2019, 135.90, 1),

-- Central Seul Carbone Standard - 6 units (purchased in 2019)
('CENT-01', NULL, 'Central Seul Carbone Standard', 2019, 70.90, 1),
('CENT-02', NULL, 'Central Seul Carbone Standard', 2019, 70.90, 1),
('CENT-03', NULL, 'Central Seul Carbone Standard', 2019, 70.90, 1),
('CENT-04', NULL, 'Central Seul Carbone Standard', 2019, 70.90, 1),
('CENT-05', NULL, 'Central Seul Carbone Standard', 2019, 70.90, 1),
('CENT-06', NULL, 'Central Seul Carbone Standard', 2019, 70.90, 1);

-- ============================================================
--  Quick verification after import:
--  SELECT model, COUNT(*) AS nb, SUM(purchase_price) AS total
--  FROM wp_locarc_stabilizations
--  GROUP BY model ORDER BY model;
--
--  Expected result:
--    Central Seul Carbone Standard  6    425,40 EUR
--    EVO 15 / XEVO 15 Recurve      27  6 882,30 EUR
--    Uni Vbar Carbone Standard      10  1 359,00 EUR
--    ---------------------------------------------
--    TOTAL                          43  8 666,70 EUR
--  (difference of ~3 EUR vs 8 669,62 EUR due to rounding: 25,90 x 43 = 1 113,70 vs 1 113,62)
-- ============================================================
