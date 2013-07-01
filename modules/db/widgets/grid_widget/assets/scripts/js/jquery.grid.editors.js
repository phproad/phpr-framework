/**
 * This script contains grid control column editor classes.
 */

(function($, undefined) {
	$.ui.grid = {editors: {}};
	
	$.ui.grid.editors.editorBase = $.Class.create({
		initialize: function() {
		},
		
		/**
		 * Initializes the cell content during the table building procedure.
		 * @param object cell Specifies a DOM td element corresponding to the cell.
		 * @param mixed cellContent Specifies the text content - either a string, 
		 * or an object with "display" and "value" fields.
		 * @param array row Current table row.
		 * @param integer cellIndex Current cell index.
		 * @param integer rowDataIndex Current cell data element index.
		 * @param mixed columnInfo Column confiruation object.
		 * The method should set the cell inner content,
		 */
		initContent: function(cell, cellContent, row, cellIndex, columnInfo, rowDataIndex) {
			if (cell.find('.cell-content-container').length == 0)
				cell.append($('<div/>').addClass('cell-content-container').attr('tabindex', 0));
			
			this.createCellValueElement(cell, cellIndex, rowDataIndex);
			this.renderContent(cell, cellContent, row, cellIndex);
		},

		/**
		 * Creates a hiden input element for holding the cell value.
		 * @param object cell Specifies a DOM td element corresponding to the cell.
		 * @param integer cellIndex Specifies a cell index.
		 * @param integer rowDataIndex Specifies a row data index.
		 */
		createCellValueElement: function(cell, cellIndex, rowDataIndex) {
			var 
				grid = cell.data('ui.grid'),
				contentContainer = cell.find('.cell-content-container');
				
			contentContainer.append($('<input type="hidden" class="cell-value"></input>').attr(
				'name', 
				grid.options.name+'['+rowDataIndex+']['+grid.options.columns[cellIndex].field+']'
			));

			contentContainer.append($('<input type="hidden" class="internal-value"></input>').attr(
				'name', 
				grid.options.name+'['+rowDataIndex+']['+grid.options.columns[cellIndex].field+'_internal]'
			));
		},
		
		/**
		 * Renders cell content in read-only mode.
		 * @param object cell Specifies a DOM td element corresponding to the cell.
		 * @param mixed cellContent Specifies the text content - either a string, 
		 * or an object with "display" and "value" fields.
		 * @param array row Current table row.
		 * @param integer cellIndex Current cell index.
		 * The method should set the cell inner content,
		 */
		renderContent: function(cell, cellContent, row, cellIndex) {
			if ($.type(cellContent) !== 'object') {
				this.setCellValue(cell, cellContent);
				this.setCellDisplayText(cell, cellContent);
			}
			else {
				this.setCellValue(cell, cellContent.value);
				this.setCellInternalValue(cell, cellContent.internal);
				this.setCellDisplayText(cell, cellContent.display);
			}
		},
		
		/**
		 * Sets cell hidden value.
		 * @param object cell Specifies the cell DOM td element.
		 * @param string value Specifies the value to set.
		 */
		setCellValue: function(cell, value) {
			cell.find('input.cell-value').attr('value', value);
			this.markCellRowSearchUpdated(cell);
		},
		
		/**
		 * Sets cell internal hidden value.
		 * @param object cell Specifies the cell DOM td element.
		 * @param string value Specifies the value to set.
		 */
		setCellInternalValue: function(cell, value) {
			cell.find('input.internal-value').attr('value', value);
			this.markCellRowSearchUpdated(cell);
		},
		
		/**
		 * Sets cell display value.
		 * @param object cell Specifies the cell DOM td element.
		 * @param string text Specifies the text to set.
		 */
		setCellDisplayText: function(cell, text) {
			var span = cell.find('span.cell-content');
			if (span.length == 0)
			{
				var contentContainer = cell.find('.cell-content-container');
				span = $('<span class="cell-content"></span>').text(text);
				contentContainer.append(span);
			} else 
				span.text(text);
		},
		
		/**
		 * Returns cell display value.
		 * @param object cell Specifies the cell DOM td element.
		 * @return string Cell display value.
		 */
		getCellDisplayText: function(cell) {
			return cell.find('span.cell-content').text();
		},
		
		/**
		 * Returns cell hidden value.
		 * @param object cell Specifies the cell DOM td element.
		 * @return string Cell hidden value.
		 */
		getCellValue: function(cell) {
			return cell.find('input.cell-value').attr('value');
		},
		
		/**
		 * Hides cell display value.
		 * @param object cell Specifies the cell DOM td element.
		 */
		hideCellDisplayText: function(cell) {
			return cell.find('span.cell-content').hide();
		},
		
		/**
		 * Shows cell display value.
		 * @param object cell Specifies the cell DOM td element.
		 */
		showCellDisplayText: function(cell) {
			return cell.find('span.cell-content').show();
		},
		
		/**
		 * Displays the cell editor, if applicable.
		 * @param object cell Specifies the cell DOM td element.
		 */
		displayEditor: function(cell) {
			this.getGrid(cell)._setCurrentRow(cell.closest('tr'));
		},
		
		/**
		 * Hides the cell editor, if applicable.
		 * @param object cell Specifies the cell DOM td element.
		 * @param object editor Specifies the editor DOM element, if applicable.
		 */
		hideEditor: function(cell, editor) {
		},
		
		/**
		 * Sets or returns the editor visibility state.
		 * @param object cell Specifies the cell DOM td element.
		 * @param boolean value Optional visibility state value. 
		 * If omitted, the function will return the current visibility state.
		 * @return boolean
		 */
		editorVisible: function(cell, value) {
			if (value === undefined)
				return cell.data('ui.grid.editor_visible') ? true : false;

			cell.data('ui.grid.editor_visible', value);
			return value;
		},
		
		/**
		 * Returns parent grid object.
		 * @param object cell Specifies the cell DOM td element.
		 */
		getGrid: function(cell) {
			return cell.data('ui.grid');
		},
		
		/**
		 * Binds standard content container keys.
		 * @param object contentContainer Specifies the content containier $ object.
		 * @param boolean allAsClick Indicates whether any key (but arrows, tab and return) should trigger the click event.
		 * @param boolean returnAsClick Indicates whether Return key should trigger the click event.
		 */
		bindContentContainerKeys: function(contentContainer, allAsClick, returnAsClick) {
			var self = this, 
				cell = contentContainer.parent();
			
			contentContainer.bind('keydown', function(ev){
				if (self.editorVisible(cell))
					return;
				
				switch (ev.keyCode) {
					case 32 :
						ev.preventDefault();
						cell.trigger('click');
					break;
					case 38 : 
						ev.preventDefault();
						self.getGrid(cell)._navigateUp(cell);
					break;
					case 40 : 
						ev.preventDefault();
						self.getGrid(cell)._navigateDown(cell);
					break;
					case 39 :
					case 37 :
						ev.preventDefault();

						if (ev.keyCode == 39)
							self.getGrid(cell)._navigateRight(cell);
						else
							self.getGrid(cell)._navigateLeft(cell);
					break;
					case 13 :
						if (returnAsClick === undefined || returnAsClick === false)
							return;
							
						ev.preventDefault();
						cell.trigger('click');
					break;
					case 9 : break;
					default :
						if (!ev.shiftKey && !ev.ctrlKey && !ev.altKey && !ev.metaKey) {
							ev.preventDefault();
							cell.trigger('click');
						} else 
							self.getGrid(cell)._handleGridKeys(ev);
					break;
				}
			});
		},
		
		markCellRowSearchUpdated: function(cell) {
			var grid = this.getGrid(cell),
				row = cell.closest('tr');
				
			if (row.length && grid)
				grid._markRowSearchUpdated(row[0].rowDataIndex);
		}
	});
	
	/*
	 * Popup editor
	 */
	
	$.ui.grid.editors.popupEditor = $.Class.create($.ui.grid.editors.editorBase, {
		initContent: function(cell, cellContent, row, cellIndex, columnInfo, rowDataIndex) {
			var self = this;
			this.base('initContent', cell, cellContent, row, cellIndex, columnInfo, rowDataIndex);
			cell.addClass('ui-grid-cell-clickable');
			
			this.bindPopupTrigger(cell, columnInfo, rowDataIndex);
		},
		
		bindPopupTrigger: function(cell, columnInfo, rowDataIndex) {
			var self = this;
			
			cell.addClass('ui-grid-cell-focusable');
			cell.addClass('ui-grid-cell-navigatable');

			this.contentContainer = cell.find('.cell-content-container');
			this.bindContentContainerKeys(this.contentContainer, false, true);
			
			this.contentContainer.bind('focus', function(ev){
				self.getGrid(cell)._setCurrentRow(cell.closest('tr'));
			});
			
			cell.bind('navigateTo', function(event, direction, selectAll){
				self.contentContainer.focus();
			});
			
			cell.bind('click', function(){
				var grid = self.getGrid(cell);
				grid._scrollToCell(cell);
				self.buildPopup(cell, columnInfo, rowDataIndex, self);
				
				return false;
			})
		},
		
		getZIndex: function(element) {
			var zElement = element.parents().filter(function(){ 
				var css = $(this).css('z-index');
				return css !== undefined && css != 'auto';
			});
			
			return parseInt(zElement.css('z-index'));
		},
		
		buildPopup: function(cell, columnInfo, rowDataIndex, editor) {
			var 
				zIndex = this.getZIndex(cell),
				grid = this.getGrid(cell);
				self = this;
			
			/*
			 * Build the overlay
			 */
			
			var overlay = $('<div class="ui-grid-overlay ui-overlay"/>');
			if (zIndex)
				overlay.css('z-index', zIndex+1);
			
			$(grid.element).append(overlay);
			$.ui.grid.popupOverlay = overlay;
			
			/*
			 * Build the popup window
			 */
			
			var tmp = $('<div/>').css('display', 'none');
			$(grid.element).append(tmp);
			grid._hideRowMenu();

			cell.find('.cell-content-container').blur();

			cell.closest('form').phpr().post(grid.actionName+'on_form_widget_event', {
				data: {
					'phpr_custom_event_name': 'on_show_popup_editor',
					'phpr_event_field': grid.options.dataFieldName,
					'phpr_popup_column': columnInfo.field,
					'phpr_grid_row_index': rowDataIndex
				}, 
				update: tmp, 
				loadIndicator: {show: false}, 
				success: function() {
					editor.displayPopup(tmp, zIndex, cell, grid);
				},
				error: function(requestObj) {
					alert(requestObj.errorMessage);
					tmp.remove();
					overlay.remove();
					$.ui.grid.popupOverlay = undefined;
				}
			}).send();
		},
		
		displayPopup: function(dataContainer, zIndex, cell, grid) {
			var 
				container = $('<div class="ui-popup-container"/>').css({
					'z-index': zIndex+2,
					'visibility': 'hidden'
				}),
				handle = $('<div class="ui-popup-handle"/>'),
				content = $('<div class="ui-popup-content-container"/>'),
				containerOffset = $(grid.element).offset(),
				cellOffset = cell.offset(),
				docHeight = $(document).height(),
				docWidth = $(document).width(),
				cellHeightOffset = docHeight - cellOffset.top,
				cellWidthOffset = docWidth - cellOffset.left,
				popupContent = dataContainer.children().first();
				
			$.ui.grid.popupContainer = container;
			$.ui.grid.popupCell = cell;

			content.append(popupContent);
			container.append(content);
			container.append(handle);
			dataContainer.remove();
			
			$(grid.element).append(container);
			
			var
				containerHeight = container.outerHeight(),
				containerWidth = container.outerWidth(),
				showBelow = cellHeightOffset > (containerHeight+40),
				showRight = cellWidthOffset > (containerWidth+50);
				
			if ((cellOffset.top - cell.height() + 15 - containerHeight) < 0)
				showBelow = true;

			var contentTop = showBelow ? 
					(cellOffset.top - containerOffset.top + cell.height() + 5 + 'px') : 
					(cellOffset.top - containerOffset.top - cell.height() + 15 - containerHeight  + 'px');
					
			var contentLeft = showRight ? 
					(cellOffset.left - containerOffset.left + container.width() + 40 - containerWidth  + 'px') :
					(cellOffset.left - containerOffset.left - container.width() + 40  + 'px');

			container.css({
				top: contentTop,
				left: contentLeft,
				visibility: 'visible'
			});
			
			if (!showBelow)
				container.addClass('above');

			if (showRight)
				container.addClass('right');
				
			cell.addClass('popup-focused');

			popupContent[0].fireEvent('popupLoaded');
			window.fireEvent('popupLoaded', popupContent[0]);
			popupContent.data('ui.gridEditor', this);
			popupContent.data('ui.gridCell', cell);
			grid._hideEditors();
			grid._disableRowAdding();
			
			$(document).bind('keydown.gridpopup', function(ev){
				if (ev.keyCode == 27)
					popupContent[0].fireEvent('onEscape');
			});
		}
	});
	
	/*
	 * Single-line text editor. This editor has optional autocomplete parameter, which could be set in the column configuration object.
	 * The parameter value could be either "predefined" or "remote". If the predefined option is specified, the column configuration
	 * object should also have the "options" array with a list of available values.
	 */
	
	$.ui.grid.editors.textEditor = $.Class.create($.ui.grid.editors.popupEditor, {
		initialize: function() {
			this.base('initialize');
		},
		
		initContent: function(cell, cellContent, row, cellIndex, columnInfo, rowDataIndex) {
			var self = this;
			this.base('initContent', cell, cellContent, row, cellIndex, columnInfo, rowDataIndex);
			cell.addClass('ui-grid-cell-navigatable');
			cell.addClass('ui-grid-cell-editable');
			
			cell.find('.cell-content-container').bind('focus.grid', function(event){
				self.displayEditor(cell, null, false, columnInfo);
			})
			
			cell.bind('click.grid navigateTo', function(event, direction, selectAll){
				selectAll = selectAll === undefined ? false : selectAll;
				self.displayEditor(cell, direction, selectAll, columnInfo);
			});
			
			if (columnInfo.editor_class !== undefined) {
				cell.data('grid.rowDataIndex', rowDataIndex);
			}
		},
		
		bindPopupTrigger: function(cell, columnInfo, rowDataIndex) {},
		
		displayEditor: function(cell, navigationDirection, selectAll, columnInfo) {
			if (this.editorVisible(cell) || this.getGrid(cell).dragStarted)
				return;
				
			this.editorVisible(cell, true);
			
			var grid = this.getGrid(cell);
			grid._scrollToCell(cell);

			var self = this,
 				editor = $('<input type="text" class="cell-editor"/>')
				.attr('value', this.getCellValue(cell))
				.css('height', cell.height()+'px'),
				editorContainer = $('<div class="cell-editor-container"/>'),
				autocomplete = columnInfo.autocomplete !== undefined ? columnInfo.autocomplete : false;
				
			grid._disableRowMenu(cell);
			editorContainer.append(editor);
			editor.data('gridContainer', editorContainer);
			
			/*
			 * Setup autocompletion
			 */
			
			if (autocomplete !== false) {
				var options = {
					appendTo: grid.element,
					position: { my: "left top", at: "left bottom", collision: "none", of: cell },
					open: function(){
						grid._pauseScrolling();
						var autocompleteObj = grid.element.find('.ui-autocomplete'),
							widthFix = 1,
							fixLeft = false;
							
						if (cell.is(':first-child')) {
							widthFix++;
							fixLeft = true;
						}
						
						var autocompleteWidth = cell.outerWidth()+widthFix;
						if (autocompleteWidth < 200)
							autocompleteWidth = 200;

						autocompleteObj.outerWidth(autocompleteWidth);
						if (fixLeft > 0)
							autocompleteObj.css('margin-left', '-1px');
					},
					close: function(){
						grid._resumeScrolling();
					},
					minLength: (columnInfo.minLength === undefined ? 1 : columnInfo.minLength)
				};
				if (autocomplete == 'predefined')
					options.source = columnInfo.options;
				else if (autocomplete == 'remote') {
					var 
						row = cell.closest('tr'),
						form = cell.closest('form'),
						action = form.attr('action');
					
					options.source = function(request, response) {
						self.autocomplete(request, response, columnInfo, cell, row, action, form, grid);
					}
				}

				editor.autocomplete(options);
			}

			/* 
			 * Setup editor events
			 */

			this.hideCellDisplayText(cell);
			
			editor.bind('blur', function(){
				window.setTimeout(function(){
					self.hideEditor(cell, editor);
				}, 200)
			});

			grid.element.bind('hideEditors', function(){
				self.hideEditor(cell, editor);
			})

			editor.keyup(function(ev){
				self.setCellValue(cell, editor.val());
			});

			editor.bind('keydown', function(ev){
				switch (ev.keyCode) {
					case 38 : 
						if (autocomplete === false)
							grid._navigateUp(cell);
					break;
					case 40 : 
						if (autocomplete === false)
							grid._navigateDown(cell);
					break;
					case 39 :
					case 37 :
						var sel = $(editor).caret();
						if (sel.end === undefined)
							sel.end = 0;

						if (ev.keyCode == 39) {
							if (sel.end == editor.attr('value').length)
							{
								ev.preventDefault();
								grid._navigateRight(cell);
							}
						} else {
							if (sel.start === undefined) {
								ev.preventDefault();
								grid._navigateLeft(cell);
							}
						}
					break;
					case 9 :
						if (!ev.shiftKey) {
							if (grid._navigateRight(cell, true))
								ev.preventDefault();
						} else {
							if (grid._navigateLeft(cell, true))
								ev.preventDefault();
						}
					break;
					case 27 :
						self.hideEditor(cell, editor);
					break;
					case 73 :
						if (ev.metaKey || ev.ctrlKey)
							grid.appendRow(cell, ev);
					break;
				}
			});
			
			/*
			 * Create button for the popup editor
			 */
			
			if (columnInfo.editor_class !== undefined) {
				var 
					button = $('<a href="javascript:;" class="ui-grid-cell-button"></a>'),
					knob = $('<span></span>'),
					heightTweak = -1,
					row = cell.parent();
					
				if (row.is(':last-child') && grid.bodyTable.hasClass('no-bottom-border'))
					heightTweak = 0;
				
				button.css({
					'height': cell.outerHeight() + heightTweak + 'px',
				})
				.append(knob);
				
				button.click(function(){
					self.hideEditor(cell, editor);
					self.buildPopup(cell, columnInfo, cell.data('grid.rowDataIndex'), self);
					return false;
				});
				
				editorContainer.append(button);
				cell.addClass('popup-button-container');
				
				editor.on('keydown', function(ev){
					if (ev.keyCode == 13 && !ev.ctrlKey) {
						button.trigger('click');
						editorContainer.blur();
						ev.preventDefault();
						return false;
					}
				});
			}

			/*
			 * Append the editor, and focus it
			 */

			cell.append(editorContainer);
			editor.focus();

			/*
			 * Set the caret position and display the eidtor
			 */

			if (!selectAll) {
				if (navigationDirection !== undefined && navigationDirection === 'left') {
					var len = editor.attr('value').length;
					editor.caret(len, len);
				} else
					editor.caret(0, 0);
			} else {
				var len = editor.attr('value').length;
				editor.caret(0, len);
			}

			this.base('displayEditor', cell);
		},
		
		hideEditor: function(cell, editor) {
			this.editorVisible(cell, false);

			var self = this,
				grid = self.getGrid(cell);
				
			this.setCellDisplayText(cell, editor.attr('value'));
			this.setCellValue(cell, editor.attr('value'));

			if (editor.data('gridContainer'))
				editor.data('gridContainer').remove();
			else
				editor.remove();

			this.showCellDisplayText(cell);
			if (grid)
				grid._enableRowMenu(cell);
		},
		
		autocomplete: function (request, response, columnInfo, cell, row, url, form, grid) {
			var formData = form.serialize(),
				rowData = {},
				custom_values = columnInfo.autocomplete_custom_values === undefined ? false : true;
			
			row.find('input.cell-value').each(function(index, input){
				var $input = $(input),
					match = /[^\]]+\[[^\]]+\]\[\-?[0-9]+\]\[([^\]]+)\]/.exec($input.attr('name'));
					
				if (match !== null)
				{
					var field_name = 'autocomplete_row_data['+match[1]+']',
						field_value = $input.attr('value'); 
					rowData[field_name] = field_value;
				}
			});

			formData += '&'+
				$.param(rowData)+
				'&autocomplete_term='+encodeURIComponent(request.term)+
				'&autocomplete_column='+columnInfo.field+
				'&autocomplete_custom_values='+custom_values+
				'&phpr_custom_event_name=on_autocomplete'+
				'&phpr_event_field='+ grid.options.dataFieldName+
				'&phpr_no_cookie_update=1';

			var lastXhr = $.ajax({
				'type': 'POST',
				'url': url,
				'data': formData,
				success: function( data, status, xhr ) {
					if ( xhr === lastXhr ) {
						response(data);
					}
				},
				dataType: 'json',
				headers: {
					'PHPR-REMOTE-EVENT': 1,
					'PHPR-POSTBACK': 1,
					'PHPR-EVENT-HANDLER': 'ev{'+grid.actionName+'on_form_widget_event}',
					'PHPR-CUSTOM-EVENT-NAME': 'on_autocomplete',
					'PHPR-EVENT-FIELD': grid.options.dataFieldName
				}
			});
		}
	});

	/*
	 * Drop-down cell editor. This editor requires the option_keys and option_values properties
	 * to be set in the column configuration object: {option_keys: [0, 1], option_values: ['Option 1', 'Option 2']}
	 * Other supported options: allowDeselect (boolean), default_text: string
	 */	
	$.ui.grid.editors.dropdownEditor = $.Class.create($.ui.grid.editors.editorBase, {
		initialize: function() {
			this.base('initialize');
		},

		initContent: function(cell, cellContent, row, cellIndex, columnInfo, rowDataIndex) {
			var self = this;
			
			this.ignoreFocus = false;
			
			this.base('initContent', cell, cellContent, row, cellIndex, columnInfo, rowDataIndex);
			cell.addClass('ui-grid-cell-navigatable');
			cell.addClass('ui-grid-cell-editable');
			cell.addClass('ui-grid-cell-focusable');
			
			this.contentContainer = cell.find('.cell-content-container');
			this.bindContentContainerKeys(this.contentContainer, true, false);
			
			this.contentContainer.bind('focus', function(ev){
				if (ev.originalEvent !== undefined)
					self.displayEditor(cell, null, columnInfo);
				else
					self.getGrid(cell)._setCurrentRow(cell.closest('tr'));
			});

			cell.bind('click.grid', function(event, direction, selectAll){
				self.displayEditor(cell, direction, columnInfo);
			});

			cell.bind('navigateTo', function(event, direction, selectAll){
				self.contentContainer.focus();
			});
		},
		
		displayEditor: function(cell, navigationDirection, columnInfo) {
			if (this.editorVisible(cell) || this.getGrid(cell).dragStarted)
				return;

			var grid = this.getGrid(cell);
			grid._scrollToCell(cell);
			
			var 
				self = this,
				gridOffset = grid.element.offset(),
				cellOffset = cell.offset(),
				offsetLeft = cellOffset.left-gridOffset.left-2,
				offsetTop = cellOffset.top-gridOffset.top-3;

			if (!cell.is(':first-child'))
				offsetLeft++;
				
			if (!grid.options.scrollable)
				offsetLeft++;

			grid._hideEditors();
			this.editorVisible(cell, true);
			this.hideCellDisplayText(cell);
			
			var select = $('<select>'),
				selectContainer = $('<div class="ui-grid-select-container">');
				
			selectContainer.css({
				'left': offsetLeft+'px',
				'top': offsetTop+'px',
				'width': (cell.width()+2)+'px',
				'height': (cell.height()+2)+'px'
			});

			if (columnInfo.default_text !== undefined)
				select.attr('data-placeholder', columnInfo.default_text);

			if (columnInfo.allow_deselect !== undefined || columnInfo.default_text !== undefined)
				select.append($('<option>').attr('value', '').text(''));

			var valueFound = false,
				currentValue = this.getCellValue(cell);
				
			$.each(columnInfo.option_keys, function(index, value){
				select.append($('<option></option>').attr('value', value).text(columnInfo.option_values[index]));
				if (currentValue == value)
					valueFound = true;
			});
			
			if (!valueFound)
				select.append($('<option></option>').attr('value', currentValue).text(currentValue));
			
			select.val(currentValue);

			selectContainer.append(select);
			grid.element.append(selectContainer);

			select.bind('change', function(){
				self.setCellDisplayText(cell, select.find('option:selected').text());
				self.setCellValue(cell, select.val());
				self.hideEditor(cell, select);
				self.ignoreFocus = true;
				self.contentContainer.focus();
				self.ignoreFocus = false;
			})
			cell.css('overflow', 'visible');
			
			var options = {allow_single_deselect: false};
			if (columnInfo.allowDeselect !== undefined)
				options.allow_single_deselect = columnInfo.allowDeselect;

			$(select).chosen(options);
			grid._disableRowMenu(cell);
			grid._pauseScrolling();

			var container = selectContainer.find('.chzn-container');
			container.css('height', (cell.height()+2)+'px');
			
			container.bind('keydown', function(ev){
				switch (ev.keyCode) {
					case 9 :
						self.hideEditor(cell, select);
						if (!ev.shiftKey) {
							if (self.getGrid(cell)._navigateRight(cell, true))
								ev.preventDefault();
						} else {
							if (self.getGrid(cell)._navigateLeft(cell, true))
								ev.preventDefault();
						}
					break;
					case 27 :
						self.hideEditor(cell, select);
						ev.preventDefault();
						self.ignoreFocus = true;
						self.contentContainer.focus();
						self.ignoreFocus = false;
					break;
				}
			});

			window.setTimeout(function(){
				$(document).bind('mousedown.gridDropdown', function(e) { 
					self.hideEditor(cell, select);
				});
			}, 300);

			this.base('displayEditor', cell);
			this.getGrid(cell).element.bind('hideEditors', function(){
				self.hideEditor(cell, select);
			});
			
			window.setTimeout(function(){
				container.trigger('mousedown');
			}, 60);
		},
		
		hideEditor: function(cell, editor) {
			var grid = this.getGrid(cell);
			if (grid === undefined)
				return;

			grid._enableRowMenu(cell);
			grid._resumeScrolling();
			
			$(document).unbind('.gridDropdown');
			editor.parent().remove();
			this.editorVisible(cell, false);
			cell.find('.chzn-container').remove();
			cell.css('overflow', '');

			var self = this;
			this.showCellDisplayText(cell);
		}
	});
	
	/*
	 * Checkbox cell editor
	 */
	
	$.ui.grid.editors.checkboxEditor = $.Class.create($.ui.grid.editors.editorBase, {
		initContent: function(cell, cellContent, row, cellIndex, columnInfo, rowDataIndex) {
			var self = this;
			this.base('initContent', cell, cellContent, row, cellIndex, columnInfo, rowDataIndex);
			cell.addClass('cell-checkbox');
			cell.addClass('ui-grid-cell-navigatable');
			
			this.hideCellDisplayText(cell);
			var editor = $('<div class="checkbox"/>')
				.attr('tabindex', 0)
				.bind('click', function(){
					var curValue = self.getCellValue(cell);
					if (curValue == 1) {
						self.setCellValue(cell, 0);
						$(this).removeClass('checked');
						self.getGrid(cell)._dropHeaderCheckboxes();
						if (columnInfo.checked_class !== undefined)
							row.removeClass(columnInfo.checked_class);
					} else {
						self.setCellValue(cell, 1);
						$(this).addClass('checked');
						if (columnInfo.checked_class !== undefined)
							row.addClass(columnInfo.checked_class);
					}
				})
				.bind('focus', function(ev){
					self.getGrid(cell)._setCurrentRow(cell.closest('tr'));
				})
				.bind('keydown', function(ev){
					switch (ev.keyCode) {
						case 32 :
						case 13 :
							editor.trigger('click');
						break;
						case 38 : 
							self.getGrid(cell)._navigateUp(cell);
						break;
						case 40 : 
							self.getGrid(cell)._navigateDown(cell);
						break;
						case 39 :
						case 37 :
							ev.preventDefault();

							if (ev.keyCode == 39)
								self.getGrid(cell)._navigateRight(cell);
							else
								self.getGrid(cell)._navigateLeft(cell);
						break;
						case 9 :
							if (!ev.shiftKey) {
								if (self.getGrid(cell)._navigateRight(cell, true))
									ev.preventDefault();
							} else {
								if (self.getGrid(cell)._navigateLeft(cell, true))
									ev.preventDefault();
							}
						break;
						default:
							self.getGrid(cell)._handleGridKeys(ev);
						break;
					}
				});
				
				if (this.getCellValue(cell) == 1)
					editor.addClass('checked');
			
			var contentContainer = cell.find('.cell-content-container');
			contentContainer.append(editor);
			
			cell.bind('click navigateTo', function(ev, direction, selectAll){
				editor.focus();
			});
		}
	});
	
	$.ui.grid.hidePopup = function() {
		if (!$.ui.grid.popupContainer !== undefined && $.ui.grid.popupOverlay !== undefined)
		{
			hide_tooltips();
			$.ui.grid.popupContainer.remove();
			$.ui.grid.popupOverlay.remove();
			$.ui.grid.popupCell.removeClass('popup-focused');
			$.ui.grid.popupCell.data('ui.grid')._enableRowAdding();
			if ($.ui.grid.popupCell.hasClass('ui-grid-cell-focusable'))
				$.ui.grid.popupCell.find('.cell-content-container').focus();
			
			$(document).unbind('keydown.gridpopup');
		}
	}
	
})(jQuery);