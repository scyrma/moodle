<?php
	$leftfootnote = get_config('theme_school', 'leftfootnote');
?>
<footer>
	<div class="container-fluid">
		<div class="row">
			<div class="span9">
				<p><?php echo $html->leftfootnote; ?>
				<p>
					<a href="<?php echo $html->leftfootnotesectionlink1; ?>"><?php echo $html->leftfootnotesection1; ?></a>
					<span>|</span>
					<a href="<?php echo $html->leftfootnotesectionlink2; ?>"><?php echo $html->leftfootnotesection2; ?></a>
					<span>|</span>
					<a href="<?php echo $html->leftfootnotesectionlink3; ?>"><?php echo $html->leftfootnotesection3; ?></a>
					<span>|</span>
					<a href="<?php echo $html->leftfootnotesectionlink4; ?>"><?php echo $html->leftfootnotesection4; ?></a>
					<span>|</span>
					<a href="<?php echo $html->leftfootnotesectionlink5; ?>"><?php echo $html->leftfootnotesection5; ?></a>
					<span>|</span>
					<a href="<?php echo $html->leftfootnotesectionlink6; ?>"><?php echo $html->leftfootnotesection6; ?></a>
				</p>
			</div>

			<div class="span3 right">
				<div id="course-footer"><?php echo $OUTPUT->course_footer(); ?></div>
        		<p class="helplink"><?php echo $OUTPUT->page_doc_link(); ?></p>
		        <?php

                if (isset($html->footnote)) {
		            echo $html->footnote;
                }
		        echo $OUTPUT->login_info();
		        echo $OUTPUT->standard_footer_html();
		        ?>
			</div>
		</div>
	</div><!-- END of .container-fluid -->
</footer><!-- END of footer -->
