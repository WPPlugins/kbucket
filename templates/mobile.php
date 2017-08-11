<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<!DOCTYPE html>
<html>
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
		<?php wp_head(); ?>
	</head>
	<body>
		<div data-role="page">
			<?php the_content(); ?>
		</div>
	</body>
</html>
