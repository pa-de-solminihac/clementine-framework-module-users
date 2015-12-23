Clementine Framework : module Users
===

Basé sur CRUD

Options
---
```ini
[module_users]
send_account_confirmation=0
send_account_notification=0
send_account_activation=0
simulate_users=0
```

Comment l'étendre ?
---

Le formulaire de creation poste sur l'url ```users/validuser```, qui appelle ```create_or_update_user()```

**Note** : la création d'un utilisateur est faite avec un minimum d'infos (avec ```addUser()```, qui renvoie juste l'id généré), et suivie d'un appel de ```modUser()``` pour compléter.

On peut donc surcharger ```usersController->create_or_update_user($request, $params = null)``` :

```php

// dans adresseUsersController.php

public function create_or_update_user($request, $params = null)
{
    $ret = parent::create_or_update_user($request, $params);
    $err = Clementine::getHelper('errors');
    if (isset($ret['user']) && $ret['user']) {
        $users = $this->_crud;
        $donnees = $users->sanitize($request->POST);
        if (isset($ret['isnew']) && $ret['isnew']) {
            // si c'est un nouvel utilisateur on crée un nouvel enregistrement adresse en base pour cet utilisateur :
            Clementine::getModel('adresse')->addAdresseForUser($ret['user']['id'], $donnees['titre']);
        }
        // on complète l'enregistrement adresse de cet utilisateur en base de données
        $id_adresse = Clementine::getModel('adresse')->modAdresseForUser($ret['user']['id'], $donnees);
        if (!$id_adresse) {
            $err->register_err('user', 'address_modification_failed', "Impossible de modifier l'adresse de cet utilisateur" . "\r\n");
        } else {
            $ret['adresse'] = $id_adresse;
        }
    }
    return $ret;
}

```
