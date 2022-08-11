<div id="toolbar-all">
	<button id="blkDelete" class="btn btn-danger"><?php echo _("Delete Selected")?></button>
	<a href="#" class="btn btn-default" data-toggle="modal" data-target="#addNumber"><i class="fa fa-plus"></i>&nbsp;&nbsp;<?php echo _("Blacklist Number")?></a>
</div>
<table id="blGrid" data-escape="true" data-toolbar="#toolbar-all" data-url="ajax.php?module=blacklist&command=getJSON&jdata=grid" data-cache="false" data-maintain-selected="true" data-show-columns="true" data-show-toggle="true" data-toggle="table" data-pagination="true" data-search="true" class="table table-striped">
    <thead>
          <tr>
			<th data-checkbox="true" data-formatter="cbFormatter"></th>
            <th data-field="number"><?php echo _("Number")?></th>
            <th data-field="description" data-formatter="descFormatter"><?php echo _("Description")?></th>
		<?php
			if ($objSmsplus) {
				$smsplusTemplate = $objSmsplus->smsplusTemplate();
				echo $smsplusTemplate['dashBlockType'];
			}
		?>
	    <th data-field="last_date" data-formatter="lastDateFormatter"><?php echo _("Last Call")?></th>
	    <th data-field="count"><?php echo _("Incoming Calls")?></th>
            <th data-formatter="linkFormatter"><?php echo _("Actions")?></th>
        </tr>
    </thead>
</table>


<script type="text/javascript">
	var cbrows = [];
	function cbFormatter(val,row,i){
		cbrows[i] = row['number'];
	}

	function linkFormatter(value,row,idx){
		var html = '<a href="#" data-toggle="modal" data-target="#addNumber" data-number="'+row['number']+'" data-description="'+row['description']+'" data-blockedtype="'+row['blockedType']+'" ><i class="fa fa-pencil"></i></a>';
		html += '&nbsp;<a href="#" id="del'+row['number']+'" data-idx="'+idx+'" data-number="'+row['number']+'" ><i class="fa fa-trash"></i></a>';
		html += '&nbsp;<a href="#" id="report'+row['number']+'" data-number="'+row['number']+'"><i class="fa fa-area-chart"></i></a>';
		return html;
	}
	function descFormatter(value,row){
		if(value == 1){
			return "";
		}else{
			return row['description'];
		}
	}
	function lastDateFormatter (value, row, idx) {
		if (row.count === 0) { return value; }
		return moment(value).tz(timezone).format(datetimeformat) + " - " + moment(value).tz(timezone).fromNow();
	}
</script>
