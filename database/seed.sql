-- ============================================================================
-- CubeTech Hub (CubeTech Innovations Assessment) - Initial Seed Data
-- ============================================================================

INSERT INTO categories (id, name, slug, description) VALUES
(1, 'Keyboards & Mice', 'keyboards-mice', 'Precision mechanical keyboards, lightweight wireless mice, and tactile desk peripherals.'),
(2, 'Audio & Studio Gear', 'audio-studio-gear', 'High-fidelity studio headphones, condenser microphones, and desktop acoustic gear.'),
(3, 'Workspace & Accessories', 'workspace-accessories', 'Essential desk mats, braided cables, USB-C hubs, and keyboard maintenance tools.');

-- 12 Seeded Products across 3 categories:
-- Includes:
--   - Exact assessment example items: PHP 120.00 (#9) and PHP 85.50 (#10)
--   - Active product with ZERO stock (#4: CubeTech AeroLight Wireless Mouse, stock = 0, is_active = 1)
--   - INACTIVE product (#12: CubeTech Legacy Trackball Pro, stock = 8, is_active = 0)
INSERT INTO products (id, category_id, name, slug, description, price_cents, stock, is_active, is_featured, image_url) VALUES
(1, 1, 'CubeTech K75 Hot-Swap Mechanical Keyboard', 'cubetech-k75-mechanical-keyboard', 'Compact 75% layout mechanical keyboard with gasket mount, hot-swappable tactile switches, PBT double-shot keycaps, and per-key white LED backlight.', 349900, 12, 1, 1, 'assets/images/prod-k75-keyboard.jpg'),
(2, 1, 'CubeTech K65 Low-Profile Wireless Keyboard', 'cubetech-k65-low-profile-keyboard', 'Ultra-slim 65% tri-mode wireless mechanical keyboard with aluminum top plate, Bluetooth 5.1 / 2.4GHz dongle, and 40-hour battery life.', 285000, 8, 1, 1, 'assets/images/prod-k65-keyboard.jpg'),
(3, 1, 'CubeTech M1 Pro Ergonomic Vertical Mouse', 'cubetech-m1-ergonomic-mouse', 'Natural handshake 57-degree vertical wireless mouse designed to reduce wrist strain during marathon coding sessions. Adjustable up to 4000 DPI.', 145000, 15, 1, 0, 'assets/images/prod-m1-mouse.jpg'),
(4, 1, 'CubeTech AeroLight 55g Gaming Mouse', 'cubetech-aerolight-gaming-mouse', 'Featherweight 55-gram e-sports wireless mouse featuring a flagship 26,000 DPI optical sensor and crisp optical microswitches. Currently out of stock.', 249900, 0, 1, 1, 'assets/images/prod-aerolight-mouse.jpg'),
(5, 2, 'CubeTech Nova ANC Studio Headphones', 'cubetech-nova-anc-headphones', 'Over-ear wireless studio monitor headphones with hybrid active noise cancellation, 40mm beryllium drivers, and plush memory-foam earcups.', 429900, 10, 1, 1, 'assets/images/prod-nova-headphones.jpg'),
(6, 2, 'CubeTech Pulse USB-C Condenser Microphone', 'cubetech-pulse-usb-microphone', 'Cardioid studio USB-C microphone with built-in pop filter, zero-latency headphone monitoring, tap-to-mute sensor, and heavy-duty desk stand.', 219900, 9, 1, 1, 'assets/images/prod-pulse-mic.jpg'),
(7, 2, 'CubeTech Clarity Dual Desktop Soundbar', 'cubetech-clarity-desktop-soundbar', 'Compact under-monitor stereo soundbar with dual passive radiators, Bluetooth 5.3 + USB audio input, and rotary volume control knob.', 169000, 14, 1, 0, 'assets/images/prod-clarity-soundbar.jpg'),
(8, 2, 'CubeTech SonicBuds Pro True Wireless Earbuds', 'cubetech-sonicbuds-tws', 'Low-latency wireless earbuds with environmental noise cancellation for clear team calls, IPX5 water resistance, and USB-C fast-charging case.', 129900, 18, 1, 0, 'assets/images/prod-sonicbuds.jpg'),
(9, 3, 'CubeTech Braided USB-C Cable (1.5m)', 'cubetech-braided-usbc-cable', 'Heavy-duty nylon-braided USB-C to USB-C 60W fast-charging and data sync cable with reinforced aluminum housings. (Assessment test item: PHP 120.00).', 12000, 25, 1, 1, 'assets/images/prod-usbc-cable.jpg'),
(10, 3, 'Keycap Puller & Switch Brush Kit', 'keycap-puller-switch-brush-kit', 'Dual-ended stainless steel wire keycap/switch puller paired with an anti-static nylon cleaning brush for mechanical keyboard maintenance. (Assessment test item: PHP 85.50).', 8550, 30, 1, 1, 'assets/images/prod-keycap-kit.jpg'),
(11, 3, 'CubeTech 7-in-1 Aluminum USB-C Hub', 'cubetech-7in1-usbc-hub', 'Sleek anodized aluminum USB-C docking hub with 4K@60Hz HDMI, 100W Power Delivery pass-through, SD/microSD card readers, and 3x USB 3.0 ports.', 159900, 11, 1, 0, 'assets/images/prod-usbc-hub.jpg'),
(12, 3, 'CubeTech Legacy Wired Trackball Pro (Discontinued)', 'cubetech-legacy-trackball-pro', 'Archived first-generation wired optical trackball controller. Marked inactive to demonstrate hidden listing and direct-link order prevention.', 89000, 8, 0, 0, 'assets/images/prod-legacy-trackball.jpg');

-- Sample Fictional Orders to demonstrate Dashboard Metrics, Order History, and Customer Derivation
INSERT INTO orders (id, order_number, customer_name, customer_email, normalized_email, contact_number, delivery_address, payment_method, order_notes, subtotal_cents, total_cents, status, stock_restored, created_at, updated_at) VALUES
(1, 'ORD-20261101-1001', 'Maria Clara Santos', 'maria.santos@example.com', 'maria.santos@example.com', '+63 917 555 0142', 'Unit 402, IT Park Tower 2, Lahug, Cebu City 6000', 'E-Wallet', 'Please leave at the lobby reception desk.', 373900, 373900, 'Completed', 0, '2026-11-01 10:15:00', '2026-11-02 16:30:00'),
(2, 'ORD-20261103-1002', 'Juan Dela Cruz', 'juan.delacruz@example.com', 'juan.delacruz@example.com', '0918-555-0199', '88 Mango Avenue, Kamputhaw, Cebu City 6000', 'Cash on Delivery', 'Call upon arrival at gate.', 219900, 219900, 'Confirmed', 0, '2026-11-03 14:20:00', '2026-11-03 15:10:00'),
(3, 'ORD-20261104-1003', 'Maria Clara Santos-Reyes', 'MARIA.SANTOS@example.com', 'maria.santos@example.com', '+63 917 555 0188', 'Unit 402, IT Park Tower 2, Lahug, Cebu City 6000', 'Bank Transfer', 'Second order with updated contact info.', 32550, 32550, 'Pending', 0, '2026-11-04 09:05:00', '2026-11-04 09:05:00');

INSERT INTO order_items (id, order_id, product_id, product_name_snapshot, unit_price_cents_snapshot, quantity, line_total_cents) VALUES
(1, 1, 1, 'CubeTech K75 Hot-Swap Mechanical Keyboard', 349900, 1, 349900),
(2, 1, 9, 'CubeTech Braided USB-C Cable (1.5m)', 12000, 2, 24000),
(3, 2, 6, 'CubeTech Pulse USB-C Condenser Microphone', 219900, 1, 219900),
(4, 3, 9, 'CubeTech Braided USB-C Cable (1.5m)', 12000, 2, 24000),
(5, 3, 10, 'Keycap Puller & Switch Brush Kit', 8550, 1, 8550);

-- Initial Activity Notifications
INSERT INTO activity_notifications (id, type, title, message, link, is_read, created_at) VALUES
(1, 'order', 'New Order Received', 'Order ORD-20261104-1003 placed by Maria Clara Santos-Reyes (PHP 325.50).', 'order.php?id=3', 0, '2026-11-04 09:05:00'),
(2, 'product', 'Stock Alert', 'Product "CubeTech AeroLight 55g Gaming Mouse" has reached 0 stock (Out of Stock).', 'products.php?search=AeroLight', 0, '2026-11-03 16:00:00'),
(3, 'order', 'Order Confirmed', 'Order ORD-20261103-1002 status advanced to Confirmed.', 'order.php?id=2', 1, '2026-11-03 15:10:00'),
(4, 'order', 'Order Completed', 'Order ORD-20261101-1001 fulfilled and completed successfully.', 'order.php?id=1', 1, '2026-11-02 16:30:00');


