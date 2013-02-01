<?php
$this->getParentBlock($data);
?>
                <!-- utilisateurs -->
                <li>
                    <a href="<?php echo __WWW__; ?>" class="nav-top-item <?php echo ((isset($data['active']) && $data['active'] == "users")) ? 'current' : ''; ?>">Utilsateurs</a>
                    <ul>
                        <li><a href="<?php echo __WWW__; ?>/users" <?php echo (isset($data['current']) && $data['current'] == "users") ? 'class="current"' : ''; ?>>Gérer les utilisateurs</a></li>
                    </ul>
                </li>
