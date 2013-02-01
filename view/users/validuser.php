<?php
if (isset($data['errors'])) {
    if (!$request->AJAX) {
?>
<div class="form_users_validnew">
    <div class="error">
<?php
    }
    $this->getBlock('users/errors/validuser', $data);
    if (!$request->AJAX) {
?>
    </div>
    <div class="spacer"></div>
</div>
<?php
    }
}
?>
