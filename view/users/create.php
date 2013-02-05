<div class="form_users_create">
    <form name="add_user" method="post" action="<?php echo __WWW__; ?>/users/validuser" enctype="multipart/form-data">
<?php 
    $this->getBlock('users/fieldsform', $data);
?>
    </form>
</div>
