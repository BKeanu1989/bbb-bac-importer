<?php

if (!defined('ABSPATH')) exit;

?>

<form action="<?php echo home_url() ?>/wp-admin/admin-post.php" enctype="multipart/form-data" method="POST">
    <input type="hidden" name="action" value="import_bacs">
    <label for="csv">CSV: </label>
    <input type="file" name="csv" accept=".csv" id="csv">
    <input type="submit" name="submit">
</form>