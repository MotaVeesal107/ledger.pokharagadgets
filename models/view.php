<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/stock.php';
require_login();

$model = trim($_GET['model'] ?? '');
if ($model === '') {
    redirect('/models/index.php');
}

$showCost = can_view_cost($pdo);
$products = get_products_with_stock($pdo, 'WHERE p.device_model = ?', [$model]);

$pageTitle = $model;
$active = 'models';
require __DIR__ . '/../includes/header.php';
?>
<div class="topbar">
  <div>
    <div class="page-title h4"><?= e($model) ?></div>
    <div class="page-subtitle"><?= count($products) ?> item(s) for this model.</div>
  </div>
  <a href="/models/index.php" class="btn btn-outline-secondary">&larr; All models</a>
</div>

<div class="row g-3">
  <?php foreach ($products as $p): $stock = (int)$p['stock']; ?>
  <div class="col-6 col-md-4 col-lg-3">
    <div class="card h-100 p-3">
      <?php if (!empty($p['photo_path'])): ?>
        <img src="/<?= e($p['photo_path']) ?>" alt="" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:6px;">
      <?php else: ?>
        <div style="width:100%;aspect-ratio:1;background:#f2f2ee;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:1.5rem;">—</div>
      <?php endif; ?>
      <div class="fw-semibold mt-2"><?= e($p['name']) ?></div>
      <?php if ($p['color']): ?><div class="small text-muted mb-1">Color: <?= e($p['color']) ?></div><?php endif; ?>
      <div class="small text-muted mb-1">SKU: <?= e($p['sku']) ?: '—' ?></div>
      <div class="mb-1">
        <?php if ($stock <= 0): ?>
          <span class="badge badge-out">Out of stock</span>
        <?php elseif ($stock <= (int)$p['low_stock_threshold']): ?>
          <span class="badge badge-low"><?= $stock ?> left</span>
        <?php else: ?>
          <span class="badge badge-instock"><?= $stock ?> in stock</span>
        <?php endif; ?>
      </div>
      <?php if ($showCost): ?>
      <div class="small text-muted">Our cost: <?= format_currency($p['cost_price_ref']) ?></div>
      <?php endif; ?>
      <div class="fw-semibold mb-2">Customer price: <?= format_currency($p['sell_price_ref']) ?></div>
      <div class="mt-auto d-flex gap-1">
        <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" title="Share to WhatsApp / Instagram / TikTok — sends the photo and customer price only, never your cost"
                onclick='shareProduct(<?= json_encode($p['name'] . ($p['color'] ? ' - ' . $p['color'] : ''), JSON_HEX_APOS) ?>, <?= json_encode(format_currency($p['sell_price_ref']), JSON_HEX_APOS) ?>, <?= json_encode($p['photo_path'] ? '/' . $p['photo_path'] : null, JSON_HEX_APOS) ?>)'>📤 Share</button>
        <a href="/products/edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$products): ?>
  <div class="col-12 text-center text-muted py-4">No products for this model.</div>
  <?php endif; ?>
</div>

<script>
async function shareProduct(name, price, photo) {
  const caption = `${name}\n${price}`;
  try {
    if (photo && window.isSecureContext && navigator.canShare) {
      const resp = await fetch(photo);
      const blob = await resp.blob();
      const ext = (blob.type.split('/')[1] || 'jpg').replace('jpeg', 'jpg');
      const file = new File([blob], `${name.replace(/[^a-z0-9]+/gi, '-')}.${ext}`, { type: blob.type });
      if (navigator.canShare({ files: [file] })) {
        await navigator.share({ files: [file], title: name, text: caption });
        return;
      }
    }
    if (navigator.share) {
      await navigator.share({ title: name, text: caption });
      return;
    }
  } catch (err) {
    if (err.name === 'AbortError') return; // user closed the share sheet
  }
  window.open(`https://wa.me/?text=${encodeURIComponent(caption)}`, '_blank');
  if (photo) {
    alert("This browser can't attach the photo automatically. WhatsApp opened with the caption ready — please attach the photo yourself, or open this page on your phone to share photo + caption together in one tap.");
  }
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
