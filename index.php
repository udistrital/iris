<?php
/*********************************************************************
    index.php

    fixed by Vi uwu.

**********************************************************************/
require('client.inc.php');
require_once INCLUDE_DIR . 'class.page.php';

$section = 'home';
require(CLIENTINC_DIR.'header.inc.php');
?>
<style>
/* === Título principal del landing === */
.thread-body h1, 
.thread-body h2 {
  color: #9e1a98ff !important; 
  text-align: center !important;
  font-weight: 700 !important;
  font-size: 1.8rem !important;
  margin-top: 1.5rem !important;
  text-transform: uppercase;
}

/* === Íconos de categorías destacadas === */
.featured-category i.icon-folder-open {
  color: #9e1a98ff!important; 
  margin-right: 8px;
  vertical-align: middle;
  transition: transform 0.3s ease, color 0.3s ease;
}
.featured-category i.icon-folder-open:hover {
  color: #9e1a98ff!important; 
  transform: scale(1.1);
}

/* === Nombres de categoría === */
.featured-category .category-name {
  color: #9e1a98ff;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

/* === Títulos de artículos === */
.featured-category .article-title a {
  color: #9e1a98ff !important;
  text-decoration: none;
  font-weight: 500;
  transition: color 0.3s ease;
}
.featured-category .article-title a:hover {
  color: #9e1a98ff !important;
}
</style>

<div id="landing_page">
  <?php include CLIENTINC_DIR.'templates/sidebar.tmpl.php'; ?>
  <div class="main-content">
    <?php
    if ($cfg && $cfg->isKnowledgebaseEnabled()) { ?>
    <div class="search-form">
      <form method="get" action="kb/faq.php">
        <input type="hidden" name="a" value="search"/>
        <input type="text" name="q" class="search" placeholder="<?php echo __('Buscar en nuestra base de conocimientos'); ?>"/>
        <button type="submit" class="green button"><?php echo __('Buscar'); ?></button>
      </form>
    </div>
    <?php } ?>

    <div class="thread-body">
      <?php
        if($cfg && ($page = $cfg->getLandingPage()))
            echo $page->getBodyWithImages();
        else
            echo '<h1>'.__('Bienvenido al Sistema Integrado de Solicitudes y Trámites').'</h1>';
      ?>
    </div>
  </div>

  <div class="clear"></div>

  <div>
    <?php
    if($cfg && $cfg->isKnowledgebaseEnabled()){
        $cats = Category::getFeatured();
        if ($cats->all()) { ?>
          <br/><br/>
          <h2><?php echo __('Artículos destacados de la base de conocimientos'); ?></h2>
    <?php
        }

        foreach ($cats as $C) { ?>
          <div class="featured-category front-page">
            <i class="icon-folder-open icon-2x"></i>
            <div class="category-name">
              <?php echo $C->getName(); ?>
            </div>
            <?php foreach ($C->getTopArticles() as $F) { ?>
              <div class="article-headline">
                <div class="article-title">
                  <a href="<?php echo ROOT_PATH; ?>kb/faq.php?id=<?php echo $F->getId(); ?>">
                    <?php echo $F->getQuestion(); ?>
                  </a>
                </div>
                <div class="article-teaser">
                  <?php echo $F->getTeaser(); ?>
                </div>
              </div>
            <?php } ?>
          </div>
    <?php
        }
    }
    ?>
  </div>
</div>

<?php require(CLIENTINC_DIR.'footer.inc.php'); ?>
