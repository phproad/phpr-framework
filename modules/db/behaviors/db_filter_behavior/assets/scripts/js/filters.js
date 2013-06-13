
function filter_add_record(linkElement) { var $ = jQuery;

	//
	// Find record ID and description
	//
	
	linkElement = $(linkElement);
	var recordId = linkElement.parent().find('input.record_id').val(),
		cells = linkElement.parent().parent().children(),
		tableBody = $('#added-filter-list'),
		recordName = [];

	$.each(cells, function(){
		var cell = $(this);
		
		if (!cell.hasClass('list-icon') && !cell.hasClass('filter-icon'))			
			recordName.push($.trim(cell.html()));
	})
	
	recordName = recordName.join(', ');

	//
	// Check whether record exists
	//
	
	var recordExists = tableBody.find('tr td input.record_id').filter(function(){ return $(this).val() == recordId; })
	if (recordExists.length)
		return false;

	//
	// Create row in the added records list
	//

	var iconCellContent = '<a class="filter-control" href="javascript:;" onclick="return filter_delete_record(this)" title="Remove filter">'
		+ '<i class="icon-minus-sign-alt"></i>'
		+ '</a>';
	
	var noDataRow = tableBody.find('tr.no-data:first');
	if (noDataRow.length)
		noDataRow.remove();
	
	var row = $('<tr />').appendTo(tableBody);

	var iconCell = $('<td />').addClass('list-icon')
		.html(iconCellContent)
		.appendTo(row);
	
	var nameCell = $('<td />').addClass('last')
		.html(recordName)
		.appendTo(row);

	$('<input />').prop({
			type: 'hidden', 
			name: 'filter_ids[]', 
			class: 'record_id'
		})
		.val(recordId)
		.appendTo(nameCell);
	
	if (!(tableBody.children().length % 2))
		row.addClass('even');
	
	return false;
}

function filter_delete_record(linkElement) { var $ = jQuery;

	linkElement = $(linkElement);
	var tableBody = $('#added-filter-list'),
		row = linkElement.parent().parent();
	
	row.remove();
	
	tableBody.children().each(function(index, el){
		var innerRow = $(this);
		innerRow.removeClass('even');
		if (index % 2)
			innerRow.addClass('even');
	});
	
	if (!tableBody.children().length) {
		var row = $('<tr />').addClass('no-data').appendTo(tableBody);
		var el = $('<td />').appendTo(row);
		el.html('No filters added');
	}
	
	return false;
}
