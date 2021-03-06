<div class="agenda__event">
	<div class="agenda__event-heading" role="tab" id="headingOne">
	  	<div class="agenda__event-title">
	        <a role="button" data-toggle="collapse" data-parent="#accordion" href="#oa-event-<?php echo $event['uid'] ?>" aria-expanded="true" aria-controls="collapseOne" title="<?php echo $event['title']['fr'] ?>">
	        	<strong><?php echo $event['firstTimeStart'] ?></strong> <?php echo wp_trim_words($event['title']['fr'], 10) ?>
	        </a>
	        <a role="button" class="pull-right" data-toggle="collapse" data-parent="#accordion" href="#oa-event-<?php echo $event['uid'] ?>" aria-expanded="true" aria-controls="collapseOne"><span class="glyphicon glyphicon-chevron-down"></span></a>
	  	</div>
	</div>
	<div id="oa-event-<?php echo $event['uid'] ?>" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingOne">
	  	<div class="agenda__event-body">
		    <?php echo $event['description']['fr'] ?>
		    <br>
		    <a href="<?php echo $event['canonicalUrl'] ?>" target="_blank">En savoir plus</a>
		</div>
	</div>
</div>