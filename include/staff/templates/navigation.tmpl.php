<?php
if ($nav && ($tabs = $nav->getTabs()) && is_array($tabs)) {
    foreach ($tabs as $name => $tab) {
        // Ajustar rutas relativas
        if ($tab['href'][0] != '/')
            $tab['href'] = ROOT_PATH . 'scp/' . $tab['href'];

        // Duplicar específicamente la pestaña de "Tickets"
        if (stripos($tab['href'], 'tickets.php') !== false) {
            // ---- Pestaña: Tickets Externos (solo Web) ----
            $href_externos = ROOT_PATH . 'scp/tickets.php?source=web';
            echo sprintf(
                '<li class="%s %s"><a href="%s">%s</a></li>' . "\n",
                isset($tab['active']) && isset($_GET['source']) && $_GET['source'] == 'web' ? 'active' : 'inactive',
                @$tab['class'] ?: '',
                $href_externos,
                'Tareas Externos'
            );

            // ---- Pestaña: Tickets Internos (todos los demás) ----
            $href_internos = ROOT_PATH . 'scp/tickets.php?source=!web';
            echo sprintf(
                '<li class="%s %s"><a href="%s">%s</a></li>' . "\n",
                isset($tab['active']) && isset($_GET['source']) && $_GET['source'] == '!web' ? 'active' : 'inactive',
                @$tab['class'] ?: '',
                $href_internos,
                'Expedientes'
            );

            // Saltar el renderizado original de "Tickets" para evitar triple duplicado
            continue;
        }

        // ---- Render normal de los demás tabs ----
        echo sprintf('<li class="%s %s"><a href="%s">%s</a>',
            isset($tab['active']) ? 'active' : 'inactive',
            @$tab['class'] ?: '',
            $tab['href'],
            $tab['desc']
        );

        // Submenús
        if (!isset($tab['active']) && ($subnav = $nav->getSubMenu($name))) {
            echo "<ul>\n";
            foreach ($subnav as $k => $item) {
                if (isset($item['id']) && !($id = $item['id']))
                    $id = "nav$k";
                if ($item['href'][0] != '/')
                    $item['href'] = ROOT_PATH . 'scp/' . $item['href'];
                echo sprintf(
                    '<li><a class="%s" href="%s" title="%s" id="%s">%s</a></li>',
                    $item['iconclass'],
                    $item['href'],
                    $item['title'] ?? null,
                    $id ?? null,
                    $item['desc']
                );
            }
            echo "\n</ul>\n";
        }
        echo "\n</li>\n";
    }
}
?>
