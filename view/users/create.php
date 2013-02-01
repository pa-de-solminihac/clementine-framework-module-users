<div class="form_users_create">
    <form id="clementine_users_add" name="clementine_users_add" method="post" action="<?php echo __WWW__; ?>/users/validuser" enctype="multipart/form-data">
<?php 
    $this->getBlock('users/fieldsform', $data);
?>
    </form>
</div>
