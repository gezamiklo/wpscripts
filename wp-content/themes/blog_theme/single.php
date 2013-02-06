<?php get_header();?>
<div id="content">
<?php
if (have_posts())
{
    while (have_posts()):
        the_post();
        get_template_part('content');
        comments_template();
    endwhile;
}?>
</div>
<?php get_sidebar();?>
<?php get_footer();?>
