<?php
$ns = $this->getModel('fonctions');
$current_key = $ns->htmlentities($data['current_key']);
if (!empty($data['alldata']['simulate_users'])) {
    if (!isset($data['alldata']['simulate_url'])) {
        $data['alldata']['simulate_url'] = __WWW__ . '/users/simulate';
    }
    $simulate_url = $ns->mod_param($data['alldata']['simulate_url'], 'id', $data['ligne']['clementine_users.id']);
    $sections = array(
        'simulatebutton' => array(
            'url' => $simulate_url,
            'icon' => 'glyphicon glyphicon-sunglasses',
            'label' => 'Simuler',
        ),
    );
    if (empty($data['crud-sections'])) {
        $data['crud-sections'] = $sections;
    } else {
        $data['crud-sections'] = array_merge($data['crud-sections'], $sections);
    }
}
$this->getParentBlock($data, $request);
