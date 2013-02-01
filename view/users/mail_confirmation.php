<html><head><style type="text/css"><!--
            body {
                font-family: Arial;
            }
--></style></head><body><?php
if (!isset($data['class'])) {
    $data['class'] = 'users';
}
$this->getBlock($data['class'] . '/mail_confirmation_body', $data);
?>
    </body>
</html>
