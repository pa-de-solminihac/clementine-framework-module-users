<div class="form_users_edit">
<?php
$ns = $this->getModel('fonctions');
if (isset($data['user'])) {
    $user = $data['user'];
?>
    <form name="add_user" method="post" action="<?php echo __WWW__; ?>/users/validuser" enctype="multipart/form-data">
<?php
    $this->getBlock('users/fieldsform', $data);
?>
    </form>
<?php
} else {
?>
L'utilisateur que vous avez demandé n'existe pas
<?php
}
?>
</div>
