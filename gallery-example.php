<?php
/*
 * Gallery Example — reference plugin demonstrating the OnePagerCMS custom
 * section type API. Registers an "Image Gallery" section type with its own
 * data table, admin form and frontend renderer.
 */

// Create the plugin's own data table on activation (lazy, idempotent).
add_action('opcms_activate_gallery-example', function () {
    include '../database/connect.php';
    $db->exec("CREATE TABLE IF NOT EXISTS gallery
        (
        specialid int PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        images TEXT NOT NULL,
        date TEXT NOT NULL
        )");
});

opcms_register_section_type('gallery', array(
    'label' => 'Image Gallery',

    // sections-registry row → section object (null skips the section).
    'build' => function (array $registryRow) {
        include '../database/connect.php';
        $select = $db->prepare('SELECT * FROM gallery WHERE specialid = :specialid');
        $select->bindValue(':specialid', $registryRow['specialid']);
        $select->execute();
        $row = $select->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return new PluginSection('gallery', $registryRow['id'], $registryRow['position'], $row['title'], array(
            'images' => $row['images'],
        ));
    },

    // Frontend fallback renderer; a theme template 'section-gallery.php' wins over this.
    'render' => function ($section, $bgcolor, $index) {
        $images = array_filter(array_map('trim', preg_split('/[\n,]+/', (string)$section->get('images', ''))));
        $html = '
                  <section class="' . $bgcolor . 'page-section" id="' . $section->getTitle() . '">
    <div class="container">
      <div class="row">
        <div class="col-lg-12 text-center">
          <h2 class="section-heading text-uppercase">' . $section->getTitle() . '</h2>
        </div>
      </div>
      <div class="row">';
        foreach ($images as $imageUrl) {
            $html .= '
        <div class="col-md-4 mb-4">
          <img class="img-fluid" src="' . htmlspecialchars($imageUrl) . '" alt="">
        </div>';
        }
        $html .= '
      </div>
    </div>
  </section>';
        return $html;
    },

    // New/Edit/Delete redirect here; core appends action= and id=.
    'form_url' => '../core/extension.php?page=gallery-form',
));

// Admin form page, dispatched by core/extension.php?page=gallery-form.
add_filter('opcms_admin_pages', function ($pages) {
    $pages['gallery-form'] = array(
        'title' => 'Image Gallery',
        'render' => function () {
            $action = isset($_GET['action']) ? $_GET['action'] : 'New';
            if (!in_array($action, array('New', 'Edit', 'Delete'), true)) {
                $action = 'New';
            }
            $id = isset($_GET['id']) ? $_GET['id'] : '';
            $title = '';
            $images = '';

            if ($action !== 'New' && $id !== '') {
                include_once '../database/SQLSectionActions.php';
                $sectionActions = new SQLSectionActions();
                $registryRow = $sectionActions->getSectionRow($id);
                if ($registryRow !== null && $registryRow['type'] === 'gallery') {
                    include '../database/connect.php';
                    $select = $db->prepare('SELECT * FROM gallery WHERE specialid = :specialid');
                    $select->bindValue(':specialid', $registryRow['specialid']);
                    $select->execute();
                    $row = $select->fetch(PDO::FETCH_ASSOC);
                    if ($row !== false) {
                        $title = $row['title'];
                        $images = $row['images'];
                    }
                }
            }

            $readonly = ($action === 'Delete') ? ' readonly' : '';
            echo '<form method="post" action="../misc/extension.php?handler=gallery-example">
        <input type="hidden" name="id" value="' . htmlspecialchars($id) . '">
        <div class="form-group">
            <label for="gallery-title">Title:</label>
            <input type="text" class="form-control" id="gallery-title" name="title" required' . $readonly . ' value="' . htmlspecialchars($title) . '">
        </div>
        <div class="form-group">
            <label for="gallery-images">Image URLs (one per line or comma separated):</label>
            <textarea class="form-control" id="gallery-images" name="images" rows="6" required' . $readonly . '>' . htmlspecialchars($images) . '</textarea>
        </div>
        <input type="submit" class="btn ' . ($action === 'Delete' ? 'btn-danger' : 'btn-success') . '" name="action" value="' . htmlspecialchars($action) . '">
    </form>';
        },
    );
    return $pages;
});

// POST handler, dispatched by misc/extension.php?handler=gallery-example
// (session-guarded and redirect-capable).
add_filter('opcms_extension_handlers', function ($handlers) {
    $handlers['gallery-example'] = function () {
        include_once '../database/SQLSectionActions.php';
        $sectionActions = new SQLSectionActions();
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $id = isset($_POST['id']) ? $_POST['id'] : '';
        $title = isset($_POST['title']) ? $_POST['title'] : '';
        $images = isset($_POST['images']) ? $_POST['images'] : '';

        if ($action === 'New') {
            if ($title === '' || $images === '') {
                header('Location: ../core/error.php?reason=criticalinput');
                return;
            }
            include '../database/connect.php';
            $count = $db->prepare('SELECT * FROM gallery ORDER BY specialid DESC LIMIT 1;');
            $count->execute();
            $lastRow = $count->fetch();
            $specialid = ($lastRow === false) ? 1 : $lastRow['specialid'] + 1;

            $insert = $db->prepare('INSERT INTO gallery (`specialid`, `title`, `images`, `date`) VALUES (:specialid, :title, :images, :date)');
            $insert->bindValue(':specialid', $specialid);
            $insert->bindValue(':title', $title);
            $insert->bindValue(':images', $images);
            $insert->bindValue(':date', date('m.d.Y'));

            ($insert->execute() && $sectionActions->addSectionEntry('gallery', $specialid))
                ? header('Location: ../core/sections.php')
                : header('Location: ../core/error.php?reason=dberror');
            return;
        }

        $registryRow = ($id !== '') ? $sectionActions->getSectionRow($id) : null;
        if ($registryRow === null || $registryRow['type'] !== 'gallery') {
            header('Location: ../core/error.php?reason=criticalinput');
            return;
        }

        if ($action === 'Edit') {
            include '../database/connect.php';
            $update = $db->prepare('UPDATE gallery SET title = :title, images = :images, date = :date WHERE specialid = :specialid');
            $update->bindValue(':specialid', $registryRow['specialid']);
            $update->bindValue(':title', $title);
            $update->bindValue(':images', $images);
            $update->bindValue(':date', date('Y-m-d H:i:s'));
            ($update->execute())
                ? header('Location: ../core/success.php?reason=sectionchanged')
                : header('Location: ../core/error.php?reason=sectionchangefailed');
            return;
        }

        if ($action === 'Delete') {
            include '../database/connect.php';
            $delete = $db->prepare('DELETE FROM gallery WHERE specialid = :specialid');
            $delete->bindValue(':specialid', $registryRow['specialid']);
            $delete->execute();
            $sectionActions->deleteSectionEntry($registryRow['id']);
            header('Location: ../core/sections.php');
            return;
        }

        header('Location: ../core/sections.php');
    };
    return $handlers;
});

// Remove all traces on uninstall.
add_action('opcms_uninstall_gallery-example', function () {
    include '../database/connect.php';
    $db->exec('DROP TABLE IF EXISTS gallery');
    include_once '../database/SQLSectionActions.php';
    $sectionActions = new SQLSectionActions();
    $sectionActions->deleteSectionEntriesByType('gallery');
});
