<!--report Modal -->
<div class="modal fade" id="numreport" tabindex="-1" role="dialog" aria-labelledby="numReportTitle" aria-hidden="true">
    <div class="modal-dialog display modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="numReportTitle"><?php echo _("Call Log") ?></h4>
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
			</div>
			<div class="modal-body">
				<table id="blReport"
				data-escape="true"
				data-cache="false"
				data-maintain-selected="true"
				data-show-toggle="true"
				data-toggle="table"
				data-pagination="true"
				data-show-refresh="true"
				data-search="true"
				data-search-align="left"
				data-height="525"
				class="table table-striped">
					<thead>
						<tr>
							<th data-field="calldate" data-sortable="true"><?php echo _("Call Date/Time")?></th>
						</tr>
					</thead>
				</table>
			</div>
		</div>
	</div>
</div>