<?php
get_header();
?>
<div id="content">
<?
if (have_posts())
{
    while ( have_posts() ) : 
        the_post();
        get_template_part( 'content', 'excerpt' );
    endwhile;
}
?>
</div>
<?php
get_sidebar();

get_footer();
?>
