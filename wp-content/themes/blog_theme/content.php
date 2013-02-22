<div class="entry">
    <h1><a href="<?php the_permalink();?>" class="post-title"><?php echo $post->post_title ?></a></h1>

    <div class="post-container">
        <?php if (has_post_thumbnail( $post->ID ) ):
		?>
        <div class="post-thumbnail">
			<?php the_post_thumbnail('excerpt_cropped' ); ?>
		</div>
        <?php endif; ?>
        <div class="post-meta">
            <p class="author-name"><a href="<?php get_author_link(true, $post->post_author);?>"><?php echo get_author_name($post->post_author); ?></a></p>
            <p class="post-date"><?php echo date('Y. M. d.',strtotime($post->post_date))?></p>
            <p class="post-comments"><a href="#comments">Hozzászólások (<?php echo get_comments_number();?>)</a></p>
        </div>

        <p class="post-text">
            <?php the_content();?>
        </p>
    </div>
</div>