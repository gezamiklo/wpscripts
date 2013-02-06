<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Sample theme</title>
<link href="<?php echo get_template_directory_uri().'/style.css'?>" rel="stylesheet" type="text/css" />
<link href="<?php echo get_template_directory_uri().'/color-default.css'?>" rel="stylesheet" type="text/css" />
<link href="<?php echo get_template_directory_uri().'/font-default.css'?>" rel="stylesheet" type="text/css" />

<?php wp_head(); ?>

</head>
<body>
    <? do_action('wp_body_start');?>
    <a href="/" id="site-link"><?php echo $blog_name = get_bloginfo('name')?></a>
<div id="container">
   
	<div id="header">
    	<h1><a href="/" class="blog-title"><?php echo $blog_name ?></a></h1>
        <img src="<?php echo get_template_directory_uri().'/header-sample.jpg'?>" id="header-image" width="980" height="100" />
    </div>