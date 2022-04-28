<div id="toolbar-all">
	<button id="blkDelete" class="btn btn-danger"><?php echo _("Delete Selected")?></button>
	<a href="#" class="btn btn-default" data-toggle="modal" data-target="#addNumber"><i class="fa fa-plus"></i>&nbsp;&nbsp;<?php echo _("Blacklist Number")?></a>
</div>
<table 
	id="blGrid"
	data-escape="true"
	data-toolbar="#toolbar-all"
	data-url="ajax.php?module=blacklist&command=getJSON&jdata=grid"
	data-cache="false"
	data-maintain-selected="true"
	data-show-columns="true"
	data-show-toggle="true"
	data-toggle="table"
	data-pagination="true"
	data-show-refresh="true"
	data-search="true"
	data-resizable="true"
	class="table table-striped">
    <thead>
          <tr>
			<th data-checkbox="true" data-formatter="cbFormatter"></th>
			<th data-field="number" data-sortable="true" data-width="300"><?php echo _("Number")?></th>
			<th data-field="description" data-formatter="descFormatter"><?php echo _("Description")?></th>
			<th data-field="last_date" data-sortable="true" data-formatter="lastDateFormatter" data-width="320"><?php echo _("Last Call")?></th>
			<th data-field="count" data-sortable="true" data-width="200"><?php echo _("Incoming Calls")?></th>
			<th data-formatter="linkFormatter" data-width="120" data-align="center"><?php echo _("Actions")?></th>
        </tr>
    </thead>
</table>