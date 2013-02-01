<?php
if (isset($data['message'])) { 
?>
<div class="form_users_delete">
    <div class="error">
        <?php echo $data['message']; ?>
    </div>
</div>
<?php
} else {
    $this->getModel('fonctions')->redirect(__WWW__ . '/users');
}
?>
