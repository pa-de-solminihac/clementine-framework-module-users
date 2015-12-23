<?php
$ns = Clementine::getModel('fonctions');
$users = Clementine::getModel('users');
$dropdown = array();
$auth = $users->getAuth();
if ($auth) {
?>
                    <ul class="dropdown-menu dropdown-user">
<?php
    if ($users->hasPrivilege('manage_users')) {
        $dropdown['Administration'] = array(
            'url' => __WWW__ . '/users',
            'icon' => '<i class="glyphicon glyphicon-pencil"></i>',
        );
    }
    if (count($dropdown)) {
        $dropdown[] = 'divider';
    }
    if (isset($_SESSION['previous_auth'])) {
        $dropdown[$_SESSION['previous_auth']['login']] = array(
            'url' => $users->getUrlLogout(),
            'icon' => '<i class="glyphicon glyphicon-sunglasses"></i><span class="text-hide">&larr;&nbsp;</span>',
        );
    } else {
        $dropdown['Déconnexion'] = array(
            'url' => $users->getUrlLogout(),
            'icon' => '<i class="glyphicon glyphicon-log-out"></i>',
        );
    }
    if (empty($data['navbar-toplinks-dropdown'])) {
        $data['navbar-toplinks-dropdown'] = $dropdown;
    } else {
        $data['navbar-toplinks-dropdown'] = $ns->array_override($dropdown, $data['navbar-toplinks-dropdown']);
    }
    Clementine::getBlock('design/menu-li', $data['navbar-toplinks-dropdown'], $request);
?>
                    </ul>
<?php
}
