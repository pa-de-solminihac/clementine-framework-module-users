<?php
$users = $this->getModel('users');
$auth = $users->getAuth();
$toplinks = array (
    $auth['login'] => array (
        'url' => '#',
        'icon' => '<i class="glyphicon glyphicon-user"></i>',
        'dropdown' => array(
            'block' => 'users/dropdown',
        ),
    )
);
if (empty($data['navbar-toplinks'])) {
    $data['navbar-toplinks'] = $toplinks;
} else {
    $data['navbar-toplinks'] = array_merge($data['navbar-toplinks'], $toplinks);
}
// lien admin
if ($users->hasPrivilege('manage_users')) {
    $sidebar = array(
        'Utilisateurs' => array(
            'url' => '#',
            'icon' => '<i class="glyphicon glyphicon-user"></i>',
            'badge' => '',
            'recursive_menu' => array(
                'Gérer les utilisateurs' => array(
                    'url' => __WWW__ . '/users',
                    'icon' => '<i class="glyphicon glyphicon-pencil"></i>',
                ),
            )
        ),
    );
    if (empty($data['navbar-sidebar'])) {
        $data['navbar-sidebar'] = $sidebar;
    } else {
        $data['navbar-sidebar'] = array_merge($data['navbar-sidebar'], $sidebar);
    }
}
$this->getParentBlock($data);
