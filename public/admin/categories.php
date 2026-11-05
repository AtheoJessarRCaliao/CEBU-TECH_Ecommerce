<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Auth.php';
require_once __DIR__ . '/../../app/Core/Repository.php';

Auth::requireAdmin();
$repo = Repository::getInstance();

$editingCategory       = null;
$blockedDeleteCategory = null;
$formName              = '';
$formDesc              = '';
$formError             = '';

if (isset($_GET['edit_id'])) {
    $editingCategory = $repo->getCategoryById((int)$_GET['edit_id']);
    if ($editingCategory) {
        $formName = $editingCategory['name'];
        $formDesc = $editingCategory['description'];
    } else {
        setFlash('error', 'Category not found.');
        header('Location: categories.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid security token.');
        header('Location: categories.php');
        exit;
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_category') {
        $catId    = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $formName = (string)($_POST['name'] ?? '');
        $formDesc = (string)($_POST['description'] ?? '');

        if ($catId === null) {
            $res = $repo->createCategory($formName, $formDesc);
            if ($res['success']) {
                setFlash('success', 'Category created successfully.');
                header('Location: categories.php');
                exit;
            } else {
                $formError = $res['error'];
            }
        } else {
            $res = $repo->updateCategory($catId, $formName, $formDesc);
            if ($res['success']) {
                setFlash('success', 'Category updated successfully.');
                header('Location: categories.php');
                exit;
            } else {
                $formError = $res['error'];
                $editingCategory = $repo->getCategoryById($catId);
            }
        }
    } elseif ($action === 'delete_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $res   = $repo->deleteCategory($catId);
        if ($res['success']) {
            setFlash('success', 'Category deleted successfully.');
            header('Location: categories.php');
            exit;
        } else {
            // Section 13: "Block deletion while any product is assigned to the category; explain why."
            $targetCat = $repo->getCategoryById($catId);
            $blockedDeleteCategory = [
                'id'    => $catId,
                'name'  => $targetCat['name'] ?? ('Category #' . $catId),
                'error' => $res['error'] ?? 'Category cannot be deleted while products are assigned to it.',
            ];
        }
    }
}

$categories = $repo->getCategories();

$pageTitle = 'Category Management';
$adminNav  = 'categories';
require_once __DIR__ . '/../../app/Views/layouts/admin_header.php';
?>

<div class="section-header" style="margin-top: 8px;">
    <div>
        <h1 class="section-title">Category Management</h1>
        <p style="color: var(--text-secondary); font-size: 0.9rem;">
            View, add, edit, and delete categories. Duplicate names (ignoring case) and deleting categories with assigned products are blocked.
        </p>
    </div>
</div>

<!-- Feedback Banner when Category Deletion is Blocked (Section 13) -->
<?php if ($blockedDeleteCategory): ?>
    <div class="feedback-card feedback-card-warning" role="alert" style="margin-bottom: 24px;">
        <div class="feedback-card-icon">&#9888;</div>
        <div class="feedback-card-content">
            <h4 class="feedback-card-title">Category Deletion Blocked (Assigned Products Dependency)</h4>
            <p class="feedback-card-desc"><?= e($blockedDeleteCategory['error']) ?></p>
        </div>
        <div class="feedback-card-action">
            <a href="products.php?category_id=<?= (int)$blockedDeleteCategory['id'] ?>" class="btn btn-warning btn-sm">
                View &amp; Reassign Products &rarr;
            </a>
        </div>
    </div>
<?php endif; ?>

<div class="cart-layout" style="grid-template-columns: 1fr 1.6fr;">
    <!-- Add / Edit Category Form -->
    <div class="card">
        <h2 class="card-title">
            <?= $editingCategory ? 'Edit Category #' . (int)$editingCategory['id'] : 'Add New Category' ?>
        </h2>

        <?php if ($formError !== ''): ?>
            <div class="alert alert-error"><?= e($formError) ?></div>
        <?php endif; ?>

        <form action="categories.php" method="post" data-disable-on-submit="true" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="save_category">
            <?php if ($editingCategory): ?>
                <input type="hidden" name="category_id" value="<?= (int)$editingCategory['id'] ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 14px;">
                <label for="cat-name" class="form-label">Category Name <span style="color: var(--danger);">*</span></label>
                <input
                    type="text"
                    id="cat-name"
                    name="name"
                    class="form-control"
                    placeholder="e.g. Monitors & Stands"
                    value="<?= e($formName) ?>"
                    required
                >
                <span style="font-size: 0.76rem; color: var(--text-muted);">
                    Must be non-blank and unique (case-insensitive).
                </span>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="cat-desc" class="form-label">Description <span style="color: var(--text-muted); font-weight: 400;">(Optional)</span></label>
                <textarea
                    id="cat-desc"
                    name="description"
                    class="form-control"
                    rows="3"
                    placeholder="Brief description shown on the home page category card..."
                ><?= e($formDesc) ?></textarea>
            </div>

            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary" data-saving-text="Saving...">
                    <?= $editingCategory ? 'Update Category' : 'Add Category' ?>
                </button>
                <?php if ($editingCategory): ?>
                    <a href="categories.php" class="btn btn-secondary">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Categories List Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table class="data-table mobile-stack-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Assigned Products</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td data-label="ID">#<?= (int)$cat['id'] ?></td>
                            <td data-label="Category Name">
                                <div style="font-weight: 700; color: var(--text-primary);"><?= e($cat['name']) ?></div>
                                <?php if (trim((string)$cat['description']) !== ''): ?>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);"><?= e($cat['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Assigned Products">
                                <a href="products.php?category_id=<?= (int)$cat['id'] ?>" style="font-weight: 700;">
                                    <?= (int)$cat['product_count'] ?> total
                                </a>
                                <span style="font-size: 0.78rem; color: var(--text-muted);">
                                    (<?= (int)$cat['active_product_count'] ?> active)
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div style="display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap;">
                                    <a href="categories.php?edit_id=<?= (int)$cat['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>

                                    <form
                                        action="categories.php"
                                        method="post"
                                        data-confirm="Delete category '<?= e(addslashes($cat['name'])) ?>'?"
                                        style="margin: 0;"
                                    >
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../app/Views/layouts/admin_footer.php'; ?>

