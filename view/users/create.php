<?php $user = ''; ?>
<div class="form_users_create">
    <form method="post" action="<?php echo __WWW__; ?>/users/validuser" enctype="multipart/form-data">
<?php 
    $this->getBlock('users/fieldsform', $user);
?>
    </form>
</div>
