<?php
if (!$nav || !($subnav=$nav->getSubMenu()) || !is_array($subnav))
    return;

$activeMenu=$nav->getActiveMenu();
if ($activeMenu>0 && !isset($subnav[$activeMenu-1]))
    $activeMenu=0;

$info = $nav->getSubNavInfo();
?>
<nav class="<?php echo @$info['class']; ?>" id="<?php echo $info['id']; ?>">
  <ul id="sub_nav">
<?php
    foreach($subnav as $k=> $item) {
        if (is_callable($item)) {
            if ($item($nav) && !$activeMenu)
                $activeMenu = 'x';
            continue;
        }
        if(isset($item['droponly'])) continue;
        $class=$item['iconclass'];
        if ($activeMenu && $k+1==$activeMenu
                or (!$activeMenu
                    && (strpos(strtoupper($item['href']),strtoupper(basename($_SERVER['SCRIPT_NAME']))) !== false
                        or ($item['urls']
                            && in_array(basename($_SERVER['SCRIPT_NAME']),$item['urls'])
                            )
                        )))
            $class="$class active";
        if (!($id=$item['id']))
            $id="subnav$k";

        //Extra attributes
        $attr = '';
        if (isset($item['attr']))
            foreach ($item['attr'] as $name => $value)
                $attr.=  sprintf("%s='%s' ", $name, $value);

        $badge = '';
        $badgeCount = isset($item['badge']) ? (int) $item['badge'] : 0;
        if ($badgeCount > 0) {
            $badgeClass = @$item['badge_class'] === 'critical'
                ? 'critical' : 'warning';
            $badgeText = $badgeCount > 99 ? '99+' : (string) $badgeCount;
            $badgeLabel = sprintf(
                _N('%d open task', '%d open tasks', $badgeCount),
                $badgeCount
            );
            $badge = sprintf(
                '<span class="task-nav-badge task-nav-badge-%s" aria-label="%s">%s</span>',
                $badgeClass,
                Format::htmlchars($badgeLabel),
                Format::htmlchars($badgeText)
            );
        }

        echo sprintf('<li class="%s"><a class="%s" href="%s" title="%s" id="%s" %s><span class="task-nav-label">%s</span>%s</a></li>',
        $item['class'], $class, $item['href'], $item['title'], $id, $attr,
        Format::htmlchars($item['desc']), $badge);
    }
?>
  </ul>
</nav>
