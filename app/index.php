<?php
declare(strict_types=1);
session_start();
$host = getenv('DB_HOST') ?: 'mysql';
$db = getenv('DB_NAME') ?: 'inventory_db';
$user = getenv('DB_USER') ?: 'inventory_user';
$pass = getenv('DB_PASSWORD') ?: '';
$pdo = null; $dbError = null;
try {
  $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);
} catch (Throwable $e) { $dbError = $e->getMessage(); }

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function redirect(): never { header('Location: /'); exit; }
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
  if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Invalid CSRF token'); }
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'product') {
      $sku = trim((string)($_POST['sku'] ?? ''));
      $name = trim((string)($_POST['name'] ?? ''));
      $unit = trim((string)($_POST['unit'] ?? 'cái'));
      $price = (float)($_POST['price'] ?? 0);
      $qty = (int)($_POST['quantity'] ?? 0);
      $min = (int)($_POST['min_quantity'] ?? 0);
      if ($sku === '' || $name === '' || $price < 0 || $qty < 0 || $min < 0) throw new RuntimeException('Vui lòng nhập dữ liệu hợp lệ.');
      $stmt = $pdo->prepare('INSERT INTO products(sku,name,unit,price,quantity,min_quantity) VALUES(?,?,?,?,?,?)');
      $stmt->execute([$sku,$name,$unit,$price,$qty,$min]);
    } elseif ($action === 'supplier') {
      $name = trim((string)($_POST['name'] ?? ''));
      if ($name === '') throw new RuntimeException('Tên nhà cung cấp không được để trống.');
      $stmt = $pdo->prepare('INSERT INTO suppliers(name,phone,email,address) VALUES(?,?,?,?)');
      $stmt->execute([$name,trim((string)($_POST['phone']??'')),trim((string)($_POST['email']??'')),trim((string)($_POST['address']??''))]);
    } elseif (in_array($action, ['in','out'], true)) {
      $productId = (int)($_POST['product_id'] ?? 0); $qty = (int)($_POST['quantity'] ?? 0);
      if ($productId < 1 || $qty < 1) throw new RuntimeException('Số lượng phải lớn hơn 0.');
      $pdo->beginTransaction();
      $stmt = $pdo->prepare('SELECT id,quantity FROM products WHERE id=? FOR UPDATE');
      $stmt->execute([$productId]); $product = $stmt->fetch();
      if (!$product) throw new RuntimeException('Không tìm thấy sản phẩm.');
      if ($action === 'out' && (int)$product['quantity'] < $qty) throw new RuntimeException('Không đủ tồn kho để xuất hàng.');
      $delta = $action === 'in' ? $qty : -$qty;
      $pdo->prepare('UPDATE products SET quantity=quantity+? WHERE id=?')->execute([$delta,$productId]);
      $pdo->prepare('INSERT INTO stock_movements(product_id,movement_type,quantity,note) VALUES(?,?,?,?)')
          ->execute([$productId, $action === 'in' ? 'IN' : 'OUT', $qty, trim((string)($_POST['note']??''))]);
      $pdo->commit();
    }
    $_SESSION['flash'] = 'Thao tác đã được lưu thành công.';
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'] = 'Không thể thực hiện: ' . $e->getMessage();
  }
  redirect();
}
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$products = []; $suppliers = []; $movements = []; $stats = ['products'=>0,'units'=>0,'low'=>0];
if ($pdo) {
  $products = $pdo->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();
  $suppliers = $pdo->query('SELECT * FROM suppliers ORDER BY id DESC')->fetchAll();
  $movements = $pdo->query('SELECT m.*,p.name product_name,p.sku FROM stock_movements m JOIN products p ON p.id=m.product_id ORDER BY m.id DESC LIMIT 10')->fetchAll();
  $stats['products'] = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
  $stats['units'] = (int)$pdo->query('SELECT COALESCE(SUM(quantity),0) FROM products')->fetchColumn();
  $stats['low'] = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE quantity <= min_quantity')->fetchColumn();
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Inventory | Quản lý kho</title>
<style>
:root{--ink:#182230;--muted:#687586;--line:#e4e9ef;--blue:#2459d3;--bg:#f5f7fb;--green:#16794a;--red:#b42318}*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,-apple-system,Segoe UI,sans-serif;background:var(--bg);color:var(--ink)}header{background:#fff;border-bottom:1px solid var(--line);padding:18px max(20px,calc((100% - 1180px)/2));display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}.brand{font-size:21px;font-weight:800}.brand span{color:var(--blue)}header small{color:var(--muted)}main{max-width:1180px;margin:28px auto;padding:0 18px}.intro{display:flex;justify-content:space-between;align-items:end;gap:16px;flex-wrap:wrap}.intro h1{margin:0;font-size:28px}.muted{color:var(--muted)}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:22px 0}.card,.panel{background:white;border:1px solid var(--line);border-radius:14px;padding:18px;box-shadow:0 3px 14px #18223008}.stat-label{color:var(--muted);font-size:13px}.stat-value{font-size:27px;font-weight:800;margin-top:4px}.layout{display:grid;grid-template-columns: minmax(0,1.6fr) minmax(300px,1fr);gap:16px}.panel{margin-bottom:16px}.panel h2{font-size:17px;margin:0 0 14px}.formgrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}label{font-size:12px;color:var(--muted);display:block;margin-bottom:4px}input,select{width:100%;padding:10px;border:1px solid #cfd7e2;border-radius:8px;font:inherit;background:white}button{border:0;background:var(--blue);color:white;padding:10px 14px;border-radius:8px;font-weight:700;cursor:pointer}button:hover{filter:brightness(.95)}.wide{grid-column:1/-1}.tablewrap{overflow:auto}table{border-collapse:collapse;width:100%;font-size:13px}th,td{text-align:left;padding:10px 8px;border-bottom:1px solid var(--line);white-space:nowrap}th{color:var(--muted);font-weight:600}.pill{display:inline-block;padding:3px 8px;border-radius:20px;background:#eaf7ef;color:var(--green);font-size:12px}.low{background:#fff0ee;color:var(--red)}.flash{padding:12px 14px;border-radius:9px;background:#eaf2ff;margin:16px 0}.error{background:#fff0ee;color:var(--red)}.sectiongap{margin-top:20px}footer{text-align:center;color:var(--muted);padding:20px}@media(max-width:850px){.layout{grid-template-columns:1fr}.stats{grid-template-columns:1fr}.intro h1{font-size:24px}}@media(max-width:480px){.formgrid{grid-template-columns:1fr}.wide{grid-column:auto}}
</style></head><body>
<header><div class="brand">▦ <span>Inventory</span> / Quản lý kho</div><small>Hệ thống thực hành · Đề tài 5</small></header>
<main><div class="intro"><div><h1>Tổng quan kho hàng</h1><div class="muted">Theo dõi hàng hóa, nhập xuất và tồn kho trong một nơi.</div></div><span class="pill">● MySQL <?= $pdo ? 'đã kết nối' : 'chưa kết nối' ?></span></div>
<?php if ($flash): ?><div class="flash <?= str_contains($flash,'Không thể')?'error':'' ?>"><?= h($flash) ?></div><?php endif; ?>
<?php if ($dbError): ?><div class="flash error">Không kết nối được cơ sở dữ liệu. Hãy kiểm tra cấu hình Docker và biến môi trường. Chi tiết kỹ thuật được ghi trong log ứng dụng.</div><?php endif; ?>
<div class="stats"><div class="card"><div class="stat-label">Mặt hàng</div><div class="stat-value"><?= $stats['products'] ?></div></div><div class="card"><div class="stat-label">Tổng đơn vị tồn kho</div><div class="stat-value"><?= $stats['units'] ?></div></div><div class="card"><div class="stat-label">Mặt hàng cần nhập thêm</div><div class="stat-value"><?= $stats['low'] ?></div></div></div>
<div class="layout"><section>
<div class="panel"><h2>Danh sách sản phẩm</h2><div class="tablewrap"><table><thead><tr><th>Mã hàng</th><th>Tên sản phẩm</th><th>Đơn vị</th><th>Giá</th><th>Tồn</th><th>Trạng thái</th></tr></thead><tbody>
<?php foreach($products as $item): ?><tr><td><?=h($item['sku'])?></td><td><?=h($item['name'])?></td><td><?=h($item['unit'])?></td><td><?=number_format((float)$item['price'],0,',','.')?> đ</td><td><?= (int)$item['quantity'] ?></td><td><span class="pill <?= (int)$item['quantity'] <= (int)$item['min_quantity']?'low':'' ?>"><?= (int)$item['quantity'] <= (int)$item['min_quantity']?'Sắp hết':'Đủ hàng' ?></span></td></tr><?php endforeach; ?>
<?php if (!$products): ?><tr><td colspan="6" class="muted">Chưa có sản phẩm. Hãy thêm sản phẩm ở biểu mẫu bên cạnh.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="panel"><h2>Lịch sử nhập / xuất gần đây</h2><div class="tablewrap"><table><thead><tr><th>Thời gian</th><th>Mã hàng</th><th>Sản phẩm</th><th>Loại</th><th>Số lượng</th><th>Ghi chú</th></tr></thead><tbody>
<?php foreach($movements as $m): ?><tr><td><?=h($m['created_at'])?></td><td><?=h($m['sku'])?></td><td><?=h($m['product_name'])?></td><td><span class="pill <?= $m['movement_type']==='OUT'?'low':'' ?>"><?= $m['movement_type']==='IN'?'Nhập':'Xuất' ?></span></td><td><?= (int)$m['quantity'] ?></td><td><?=h($m['note'])?></td></tr><?php endforeach; ?>
<?php if (!$movements): ?><tr><td colspan="6" class="muted">Chưa có giao dịch kho.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="panel"><h2>Nhà cung cấp</h2><div class="tablewrap"><table><thead><tr><th>Tên</th><th>Điện thoại</th><th>Email</th><th>Địa chỉ</th></tr></thead><tbody>
<?php foreach($suppliers as $s): ?><tr><td><?=h($s['name'])?></td><td><?=h($s['phone'])?></td><td><?=h($s['email'])?></td><td><?=h($s['address'])?></td></tr><?php endforeach; ?>
<?php if (!$suppliers): ?><tr><td colspan="4" class="muted">Chưa có nhà cung cấp.</td></tr><?php endif; ?></tbody></table></div></div>
</section><aside>
<div class="panel"><h2>＋ Thêm sản phẩm</h2><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="product"><div class="formgrid">
<div><label>Mã hàng (SKU) *</label><input name="sku" required maxlength="50" placeholder="SP-001"></div><div><label>Tên sản phẩm *</label><input name="name" required maxlength="150" placeholder="Tên hàng hóa"></div>
<div><label>Đơn vị</label><input name="unit" value="cái" maxlength="30"></div><div><label>Đơn giá (VNĐ)</label><input type="number" name="price" min="0" step="1000" value="0" required></div>
<div><label>Số lượng ban đầu</label><input type="number" name="quantity" min="0" value="0" required></div><div><label>Ngưỡng cảnh báo</label><input type="number" name="min_quantity" min="0" value="5" required></div><div class="wide"><button type="submit">Lưu sản phẩm</button></div></div></form></div>
<div class="panel"><h2>＋ Thêm nhà cung cấp</h2><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="supplier"><div class="formgrid">
<div class="wide"><label>Tên nhà cung cấp *</label><input name="name" required></div><div><label>Điện thoại</label><input name="phone"></div><div><label>Email</label><input type="email" name="email"></div><div class="wide"><label>Địa chỉ</label><input name="address"></div><div class="wide"><button type="submit">Lưu nhà cung cấp</button></div></div></form></div>
<div class="panel"><h2>⇅ Ghi nhận nhập / xuất</h2><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><div class="formgrid"><div class="wide"><label>Sản phẩm</label><select name="product_id" required><option value="">Chọn sản phẩm</option><?php foreach($products as $item): ?><option value="<?=$item['id']?>"><?=h($item['sku'].' — '.$item['name'].' (tồn '.$item['quantity'].')')?></option><?php endforeach; ?></select></div><div><label>Số lượng</label><input type="number" name="quantity" min="1" value="1" required></div><div><label>Loại giao dịch</label><select name="action"><option value="in">Nhập kho</option><option value="out">Xuất kho</option></select></div><div class="wide"><label>Ghi chú</label><input name="note" maxlength="255" placeholder="Lý do / mã phiếu"></div><div class="wide"><button type="submit">Xác nhận giao dịch</button></div></div></form></div>
</aside></div></main><footer>Inventory · Đề tài thực hành 5 · Triển khai và Quản trị Hệ thống Phần mềm</footer></body></html>
