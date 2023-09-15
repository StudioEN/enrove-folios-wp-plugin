<?php
use Groove\Themes\Theme_2;
$theme = new Theme_2();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<?php wp_head();  ?>
</head>

<body <?php body_class(); ?>>
	<?php $theme->display_theme(); ?>
	<?php wp_footer(); ?>
</body>
</html>
