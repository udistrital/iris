<?php
    $BUTTONS = isset($BUTTONS) ? $BUTTONS : true;
    ?>

    <style>
    .sidebar .front-page-button a.button,
    .sidebar .front-page-button a.button:visited {
    font-weight: 600 !important;
    border-radius: 6px !important;
    padding: 10px 18px !important;
    text-align: center !important;
    text-transform: uppercase !important;
    display: inline-block !important;
    text-decoration: none !important;
    transition: background 0.3s ease, transform 0.2s ease !important;
    width: 100% !important;
    box-sizing: border-box !important;
    }

    /* Botón Crear solicitud */
    .sidebar .front-page-button a.blue.button,
    .sidebar .front-page-button a.blue.button:visited {
    background: #75c40eff !important;  
    color: #fff !important;
    }
    .sidebar .front-page-button a.blue.button:hover {
    background: #5c970fff !important;
    transform: translateY(-2px);
    }

    /* Botón Consultar estado*/
    .sidebar .front-page-button a.green.button,
    .sidebar .front-page-button a.green.button:visited {
    background: #84007dff !important;
    color: #fff !important;
    }
    .sidebar .front-page-button a.green.button:hover {
    background: #9e1a98ff!important;
    transform: translateY(-2px);
    }

    /* Botón buscar*/
    .green.button {
    background-color: #84007dff; 
    width: 20%;
    box-sizing: border-box;
    }
    .green.button:hover {
    background-color: #9e1a98ff;
    transform: translateY(-2px);
    }

    /* Íconos dentro de botones */
    .sidebar .front-page-button a.button i {
    margin-right: 6px !important;
    }

    /* Espaciado */
    .sidebar .front-page-button { margin-bottom: 2rem !important; }
    .sidebar .front-page-button p { margin: 0.5rem 0 !important; }
    .sidebar .content {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 1rem 1.2rem;
    font-family: 'Inter', 'Roboto', sans-serif;
    color: #222;
    }

    /* Títulos de las secciones ("Preguntas destacadas", "Otros recursos") */
    .sidebar .content .header {
    color: #9e1a98ff;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 1rem;
    margin-bottom: 0.7rem;
    border-bottom: 2px solid #9e1a98ff;
    padding-bottom: 0.3rem;
    }

    /* Cada enlace dentro de las listas */
    .sidebar .content a {
    color: #9e1a98ff; 
    text-decoration: none;
    display: block;
    padding: 6px 0;
    transition: color 0.3s ease, transform 0.2s ease;
    font-weight: 500;
    }

    /* Hover en los enlaces */
    .sidebar .content a:hover {
    color: #3855daff;
    transform: translateX(4px);
    }

    /* Espaciado entre secciones */
    .sidebar .content section {
    margin-bottom: 1.2rem;
    }

    /* Contenedor general */
    .sidebar {
    max-width: 320px;
    }

    /* Fondo del bloque completo de botones y contenido */
    .sidebar .front-page-button {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    }
    </style>


   
<div class="sidebar pull-right">
  <?php if ($BUTTONS) { ?>
    <div class="front-page-button flush-right">
      <p>
        <?php
        if ($cfg->getClientRegistrationMode() != 'disabled'
            || !$cfg->isClientLoginRequired()) { ?>
          <a href="open.php" class="blue button">
            <i class="fa fa-plus-circle"></i> Crear nueva solicitud docentes y estudiantes
          </a>
        <?php } ?>
      </p>
      <p>
        <a href="view.php" class="green button">
          <i class="fa fa-search"></i> Consultar estado solicitud docentes y estudiantes
        </a>
      </p>
    </div>
  <?php } ?>

  <div class="content">
    <?php
    if ($cfg->isKnowledgebaseEnabled()
        && ($faqs = FAQ::getFeatured()->select_related('category')->limit(5))
        && $faqs->all()) { ?>
        <section>
            <div class="header">Acerca de Iris</div>

            <div>
                <a href="https://www.youtube.com/watch?v=gD435cI9w-s" target="_blank" rel="noopener noreferrer">¿Qué es Iris?</a>
            </div>

            <div>
                <a href="https://www.youtube.com/watch?v=wVDBDdIeRR8" target="_blank" rel="noopener noreferrer">¿Cómo crear tus tareas en IRIS?</a>
            </div>

            <div>
                <a href="https://www.youtube.com/watch?v=TYaWn3V1gew&t=15s" target="_blank" rel="noopener noreferrer">Asignar tareas en IRIS desde el rol de Administrador</a>
            </div>

            <div>
                <a href="https://www.youtube.com/watch?v=H0WmBmFZwE8&t=1s" target="_blank" rel="noopener noreferrer">¿Cómo consultar y dar respuesta a tus tareas en IRIS?</a>
            </div>

            <div>
                <a href="https://www.youtube.com/watch?v=DJKAGKjFoVk" target="_blank" rel="noopener noreferrer">¿Cómo enviar y cerrar tus tareas en IRIS?</a>
            </div>

            <div>
                <a href="https://youtu.be/-JcspMWd2h8" target="_blank" rel="noopener noreferrer">¿Cómo exportar el listado de tus tareas en IRIS?</a>
            </div>
        </section>
    <?php } ?>

    <?php
    $resources = Page::getActivePages()->filter(array('type'=>'other'));
    if ($resources->all()) { ?>
      <section>
        <div class="header">Otros recursos</div>
        <?php foreach ($resources as $page) { ?>
          <div>
            <a href="<?php echo ROOT_PATH; ?>pages/<?php echo $page->getNameAsSlug(); ?>">
              <?php echo $page->getLocalName(); ?>
            </a>
          </div>
        <?php } ?>
      </section>
    <?php } ?>
  </div>
</div>