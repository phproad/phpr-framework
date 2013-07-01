/**
 * This script contains the grid control.
 * The plugin should be applied to a DIV element.
 * Grid table will be built inside this element.
 * The options object should be specified in the followingi format:
 * {
 *   columns: [
 *     {title: 'Id', field: 'id', type: 'text', width: '170'},
 *     {title: 'Name', field: 'name', type: 'text', width: '170'}
 *   ],
 *   data: [
 *     [1, "First row"]
 *     [2, {display: "Second row", value: any_custom_data}]
 *   ],
 *   scrollable: true,
 *   allowAutoRowAdding: false,
 *   sortable: true,
 *   scrollable: true,
 *   name: 'myGrid' // This value will be use in hidden input names.
 * }
 * The "type" property of column specifies a column editor to be used for the column.
 * The "data" property is not required. However if the data is not provided, the DOM element
 * the plugin is applied to should contain a table markup with THEAD and TBODY elements. Head columns
 * should match the data provided in the "columns" property. If the table already exists, the "data" 
 * element is ignored. Cells can contain a hidden input element with the "cell-value" class.
 * See jquery.backend.grid.editors.js for the list of supported editors.
 * The editor class name is calculated as the column type name + "Editor", so that 
 * "text" is converted to "textEditor".
 */

(function($, undefined) {
	$.widget('ui.grid', {
		
		options: { 
			allowAutoRowAdding: true,
			name: 'grid',
			dataFieldName: null,
			sortable: false,
			scrollable: false,
			scrollableViewportClass: null,
			rowsDeletable: false,
			deleteRowMessage: 'Do you really want to delete this row?',
			useDataSource: false,
			focusFirst: false
		},
		
		/**
		 * Updates table column widths.
		 */
		alignColumns: function(row) {
			if (this.options.useDataSource)
				return;
			
			var widths = [],
				self = this;
			this.headTable.find('th div.ui-grid-head-content').each(function(index, div){
				widths.push($(div).outerWidth());
			});

			if (row === undefined) {
				this.bodyTable.find('tr:first-child').each(function(rowIndex, row) {
					$(row).find('td').each(function(index, td) {
						 if (index < (widths.length-1)) {
							var 
								tweak = 0,
								$td = $(td);

							if ($.browser.webkit && !self.isChrome && index > 0)
								tweak = 1;

							$td.css('width', (widths[index] + tweak) + 'px');
							$td.find('span.cell-content').css('width', (widths[index] + tweak - 18) + 'px');
						 }
					});
				})
			} else {
				row.find('td').each(function(index, td) {
					if (index < (widths.length-1)) {
						var tweak = 0;
						
						if ($.browser.webkit && index > 0)
							tweak = 1;
						
						$(td).css('width', (widths[index] + tweak) + 'px');
					}
				})
			}
		},
		
		/**
		 * Adds new row to the table.
		 * @param array data Optional data array. If the second parameter is required, pass false to this parameter.
		 * @param string position Specifies the new row position. 
		 * Possible values: 'top', 'bottom', 'above', 'below'. 'bottom' is the default value.
		 */
		addRow: function(data, position) {
			if (!this.options.allowAutoRowAdding)
				return null;
				
			if (this._isNoSearchResults())
				return false;

			var position = position === undefined ? 'bottom' : position;
			
			if (data == undefined || data === false) {
				data = [];
				for (var index=0; index<this.options.columns.length; index++)
					data.push('');
			}

			var 
				row = this._buildRow(data, position),
				offset = 'bottom';

			if (position == 'top')	
				offset = 'top';
			else if (position == 'above' || position == 'below') {
				if (this.currentRow === undefined || !this.currentRow[0].parentNode)
					offset = 'bottom';
				else
					offset = 'relative';
			}
			
			this.alignColumns(row);

			if (!this.options.useDataSource)
				this._updateScroller(offset);

			this._fixBottomBorder();
			
			this._assignRowEventHandlers(row);
			this._setCurrentRow($(row));
			
			if (this.options.useDataSource) {
				var recordCount = parseInt(this.element.find('.pagination .row-count').text());
				if (recordCount != NaN)
					this.element.find('.pagination .row-count').text(recordCount+1);
			}
			
			return row;
		},
		
		/**
		 * Deletes a current row
		 */
		deleteRow: function() {
			var self = this;
			if (!this.currentRow)
				return false;
				
			var currentPage = 0,
				row = this.currentRow;
			
			if (self.options.useDataSource)
				currentPage = self._getCurrentPage();

			var cell = this.currentRow.find('td:first'),
				rowIndex = this.bodyTable.children('tr').index(this.currentRow),
				cellIndex = this.currentRow.children('td').index(cell);

			if (cell.length && !this.options.useDataSource)
				this._navigateUp(cell);

			self._deleteRow(row, true, function(){
				if (self.options.useDataSource) {
					if (cell) {
						var row = (currentPage == self._getCurrentPage()) ? 
								self.bodyTable.find('tr:eq('+rowIndex+')') : 
								self.bodyTable.find('tr:last');

						if (!row.length)
							row = self.bodyTable.find('tr:last');

						if (row.length) {
							self._initRow(row)
							row.find('td:eq('+cellIndex+')').trigger('navigateTo');
						}
					}
				}
			});
		},
		
		appendRow: function(currentCell, ev) {
			var self = this;
			
			if (!this.options.useDataSource)
				return false;
			
			if (self.disableRowAdding !== undefined && self.disableRowAdding)
				return false;
				
			this._hideEditors();
			
			var cell = currentCell !== undefined ? currentCell : ((ev !== undefined && ev.target !== undefined) ? $(ev.target).closest('table.ui-grid-body tr td') : null);
			var index = (cell && cell !== null && cell.length) ?  
					cell.parent().children('td').index(cell) :
					0;
				
			self._createRowInTheEnd(function(nextRow){
				if (!nextRow.length)
					return;

				self._initRow(nextRow);
				if (index !== null)
					nextRow.find('td:eq('+index+')').trigger('navigateTo', ['left']);
			});
			
			return true;
		},
		
		rebuild: function() {
			this._build();
		},
		
		focusFirst: function() {
			if (this._hasRows()) {
				var firstRow = this.bodyTable.find('tr:first'); 
				this._initRow(firstRow);
				window.setTimeout(function(){
					firstRow.find('td.ui-grid-cell-navigatable:first').trigger('navigateTo');
				}, 100);
			}
		},

		_create: function() {
			$(this.element).data('ui.grid', this);
			this.isChrome = $('body').hasClass('chrome');
			
			this._build();
			this.dragStarted = false;

			var self = this;
			
			$(window).bind('phpr_widget_response_data', function(event, data){
				self._handleWidgetResponseData(data);
			});
		},
		
		_build: function() {
			if (this.options.columns === undefined) {
				this._trace('Data grid has no columns. Exiting.');
				return;
			}
			
			if (this.element.find('input.grid-table-disabled').length) {
				if (this.headTable !== undefined && this.headTable && !this.options.useDataSource)
					this.headTable.remove();
				
				return;
			}
			
			this.element.addClass('ui-grid');
			this.existingTable = this.element.find('table');
			this.actionName = this.element.find('input.grid-event-handler-name').attr('value');
			
			if (this.options.useDataSource)
				this.container = this.element.find('.ui-grid-table-container');

			this._buildHead();
			this._buildBody();
			
			if (this.options.useDataSource && this.built === undefined) {
				var columnGroupsTitleHeight = this.headTable.find('.ui-column-group-row').height();
				if (columnGroupsTitleHeight > 0)
					columnGroupsTitleHeight += 1;
				this.container.css('height', (this.bodyTable.find('tr:first').height()*(this.options.pageSize+1)+2)+columnGroupsTitleHeight+'px');
				
				var scroller = this.element.find('.ui-grid-h-scroll');
				scroller.scrollLeft(0);
				
				this._bindSearch();
			}
			
			if (!this.options.useDataSource) {
				this.alignColumns();
			
				var self = this;
				window.setTimeout(function(){
					self._updateScroller();
				}, 200);
			
				$(window).bind('resize', function(){
					self.alignColumns();
				});
			}

			if (this.built === undefined && this.options.focusFirst)
				this.focusFirst();
			
			this.built = true;
		},
		
		_updateScroller: function(direction){
			if (this.options.scrollable)
				this.scroller.scrollbar('update', direction);
		},
		
		_buildHead: function() {
 			if (!this.options.useDataSource) {
				this.headTable = $('<table class="ui-grid-head"></table>');
				var row = $('<tr></tr>');

				this.headTable.append($('<thead></thead>').append(row));
				
				$.each(this.options.columns, function(index, columnData){
					var 
						cell = $('<th></th>'),
						content = $('<div class="ui-grid-head-content"></div>');

					cell.append(content);
					content.text(columnData.title);
					if (columnData.width !== undefined)
						cell.css('width', columnData.width + 'px');

					if (columnData.align !== undefined)
						cell.addClass(columnData.align);

					row.append(cell);
				});

				this.element.find('table').find('thead').remove();
				this.element.prepend(this.headTable);
			} else
				this.headTable = this.element.find('table').find('thead');
				
			if (this.built === undefined)
				this._initHeaderCheckboxes();
		},
		
		_buildBody: function() {
			var self = this;

			this.bodyTable = this.existingTable.length ? this.existingTable.find('tbody') : $('<table class="ui-grid-body"></table>');

			if (!this.existingTable.length) {
				$.each(this.options.data, function(rowIndex, rowData) {
					self._buildRow(rowData);
				});
			} else {
				this.bodyTable.addClass('ui-grid-body');
				
				if (!this.options.useDataSource || this.built === undefined)
					this.bodyTable.bind('mouseover', function(ev){
						if (ev.target !== undefined)
							self._initRow($(ev.target).closest('tr'));
					});
			}

			if (this.options.scrollable) {
				this.scrollablePanel = this.element.find('div.grid-scroll-area');

				this.scroller = this.scrollablePanel.scrollbar();
				
				this.viewport = this.scrollablePanel;
				this.overview = this.bodyTable;
				
				this.scrollablePanel.bind('onScroll', function(){
					self._hideRowMenu();
					self._hideEditors();
				});
				
				this.scrollablePanel.bind('onBeforeWheel', function(event){
					if (self._scrollingPaused !== undefined && self._scrollingPaused)
						event.preventDefault();
				});
			} else
				this.headTable.after(this.bodyTable);

			if ((!this.options.useDataSource || this.built === undefined) && !this._hasRows())
				this.addRow();

			this._assignRowEventHandlers();

			if (this.options.sortable) {
				this.dragging = false; 

				this.bodyTable.addClass('ui-grid-draggable');
				$(document).bind('mousemove', function(event){self._mouseMove(event);});
				$(document).bind('mouseup', function(event){self._mouseUp(event);});
			}

			if (this.built === undefined) {
				this.bodyTable.bind('mouseleave', function(event){
					self._hideRowMenu(event);
				})

				this._assignBodyEventHandlers();
				this._assignDocumentHandlers();
			}
			
			this._fixBottomBorder();
		},
		
		_buildRow: function(rowData, position) {
			var 
				row = $('<tr></tr>'),
				self = this,
				position = position === undefined ? 'bottom' : position;
				
			this.rowsAdded = this.rowsAdded === undefined ? 1 : this.rowsAdded+1;

			$.each(rowData, function(cellIndex, cellData){
				var cell = $('<td></td>').data('ui.grid', self);
				self._getColumnEditor(cellIndex).initContent(cell, cellData, row, cellIndex, self.options.columns[cellIndex], -1*self.rowsAdded);
				row[0].rowDataIndex = -1*self.rowsAdded;
				
				if (self.options.columns[cellIndex].align !== undefined)
					cell.addClass(self.options.columns[cellIndex].align);

				if (self.options.columns[cellIndex].cell_css_class !== undefined)
					cell.addClass(self.options.columns[cellIndex].cell_css_class);

				if (self.options.rowsDeletable && rowData.length == cellIndex+1)
					self._createRowMenu(row, cell, self);
					
				if (self.options.columns[cellIndex].default_text !== undefined)
					cell.find('span.cell-content').text(self.options.columns[cellIndex].default_text);

				row.append(cell);
			});
			
			row[0].grid_initialized = true;

			if (position == 'bottom')
				self.bodyTable.append(row);
			else if (position == 'top')
				self.bodyTable.prepend(row);
			else if (position == 'above' || position == 'below') {
				if (self.currentRow === undefined || !self.currentRow[0].parentNode)
					self.bodyTable.append(row);
				else {
					if (position == 'above')
						row.insertBefore(self.currentRow);
					else
						row.insertAfter(self.currentRow);
				}
			}
			
			this._markRowSearchUpdated(-1*self.rowsAdded);
			
			return row;
		},

		_fixBottomBorder: function() {
			if (this.options.scrollable) {
				if (this.viewport.height() > this.overview[0].offsetHeight)
					this.bodyTable.closest('table').removeClass('no-bottom-border');
				else
					this.bodyTable.closest('table').addClass('no-bottom-border');
			} else if (this.options.useDataSource) {
				if (this.bodyTable.find('tr').length == this.options.pageSize)
					this.bodyTable.closest('table').addClass('no-bottom-border');
				else
					this.bodyTable.closest('table').removeClass('no-bottom-border');
			} 
		},
		
		_hasRows: function() {
			var tbody = this.bodyTable[0].tagName == 'TBODY' ? this.bodyTable : this.bodyTable.find('tbody'),
				i = 0;
			if (!tbody.length)
				return false;
				
			for (i=0; i< tbody[0].childNodes.length; i++)
				if (tbody[0].childNodes[i].nodeName == 'TR')
					return true;
					
			return false;
		},
		
		_initRow: function(row) {
			if (row[0].grid_initialized !== undefined && row[0].grid_initialized)
				return;
				
			row[0].grid_initialized = true;
			
			var block_delete_input = row.find('input.block_delete_message');
			if (block_delete_input.length && block_delete_input.val() != '')
				row[0].block_delete_message = block_delete_input.val();
			
			var cells = row.find('td'),
				self = this;
			
			$.each(cells, function(cellIndex, cellElement){
				var 
					cell = $(cellElement),
					valueInput = cell.find('input.cell-value'),
					internalInput = cell.find('input.internal-value'),
					rowDataIndex = cell.find('input.row-index').val(),
					cellData = {
						display: cell.text().trim(),
						value: valueInput.length ? valueInput.attr('value') : cell.text(),
						internal: internalInput.val()
					};

				cell.data('ui.grid', self);
				cell.text('');
				row[0].rowDataIndex = rowDataIndex;
				
				self._getColumnEditor(cellIndex).initContent(cell, cellData, row, cellIndex, self.options.columns[cellIndex], rowDataIndex);
				
				if (self.options.rowsDeletable && cells.length == cellIndex+1)
					self._createRowMenu(row, cell, self);
			});
		},
		
		_createRowMenu: function(row, cell, grid) {
			var 
				contentContainer = cell.find('.cell-content-container');
				menu = $('<span class="ui-grid-menu"></span>'),
				deleteLink = $('<a class="menu-delete">Delete</a>').attr('tabindex', 0).bind('click', {grid: grid}, function(ev){
					ev.stopImmediatePropagation();
					grid._deleteRow(row);
					return false;
				});
				
			menu.append(deleteLink);
			contentContainer.append(menu);
		},
		
		_assignRowEventHandlers: function(row) {
			if (this.options.sortable || this.options.rowsDeletable) {
				if (row === undefined)
					row = this.bodyTable.find('tr');
					
				var self = this;
				
				if (this.options.sortable) {
					row.bind('mousedown', function(event){
						self._dragStart(event);
					});
				}
				
				if (this.options.rowsDeletable) {
					row.bind('mouseenter', function(event){self._rowMouseEnter(event);});
					row.bind('mouseleave', function(event){self._rowMouseLeave(event);});
				}
			}
		},
		
		_assignDocumentHandlers: function() {
			var self = this;
			
			$(document).bind('keydown.grid', function(ev){
				return self._handleGridKeys(ev);
			})
		},
		
		_handleGridKeys: function(ev) {
			var self = this;
			
			if (self.options.useDataSource) {
				if (self.element.closest('html').length == 0)
					return;

				if ((ev.metaKey || ev.ctrlKey) && ev.keyCode == 73) {
					if (self.appendRow(null, ev)) {
						ev.preventDefault();
						return false;
					}
				}
			}
		},
		
		_assignBodyEventHandlers: function() {
			var self = this;
			
			this.bodyTable.bind('keydown', function(ev){
				if (ev.keyCode == 9) {
					if (self._isNoSearchResults())
						return false;
					
					if (ev.target !== undefined && $(ev.target).hasClass('cell-content-container')) {
						if (!ev.shiftKey)
							self._navigateRight($(ev.target).parent(), false);
						else
							self._navigateLeft($(ev.target).parent(), false);
						
						ev.preventDefault();
						return false;
					}
				}
				
				if ((ev.metaKey || ev.ctrlKey)) {
					if (ev.target !== undefined) {
						if (self._isNoSearchResults())
							return false;
						
						var cell = $(ev.target).closest('table.ui-grid-body tr td');
						
						if (ev.keyCode == 68) {
							if (!self.options.rowsDeletable)
								return false;
								
							if (self._isNoSearchResults())
								return false;
							
							var 
								row = $(ev.target).closest('table.ui-grid-body tr'),
								cellIndex = row.children('td').index(cell),
								rowIndex = self.bodyTable.children('tr').index(row),
								currentPage = 0;
								
							if (self.options.useDataSource)
								currentPage = self._getCurrentPage();

							if (cell.length && !self.options.useDataSource)
								self._navigateUp(cell);

							if (row.length)
								self._deleteRow(row, true, function(){
									if (self.options.useDataSource) {
										if (cell) {
											var row = (currentPage == self._getCurrentPage()) ? 
													self.bodyTable.find('tr:eq('+rowIndex+')') : 
													self.bodyTable.find('tr:last');

											if (!row.length)
												row = self.bodyTable.find('tr:last');

											if (row.length) {
												self._initRow(row)
												row.find('td:eq('+cellIndex+')').trigger('navigateTo');
											}
										}
									}
								});
								
							ev.preventDefault();
							return false;
						} else if (ev.keyCode == 73) {
							if (!self.options.allowAutoRowAdding)
								return false;
								
							if (self.options.useDataSource)
							 	return false;
							
							self.addRow(false, 'above');

							if (cell.length)
								self._navigateUp(cell);

							ev.preventDefault();
							return false;
						}
					}
				}
			})
		},
		
		_deleteRow: function(row, noMessage, onComplete) {
			if (this._isNoSearchResults())
				return false;
			
			row.addClass('ui-selected');
			
			if (noMessage === undefined || noMessage === false)
				if (!confirm(this.options.deleteRowMessage)) {
					row.removeClass('ui-selected');
					return;
				}
				
			if (row[0].block_delete_message !== undefined) {
				alert(row[0].block_delete_message);
				return;
			}
				
			if (!this.options.useDataSource) {
				row.remove();
				this._hideRowMenu();

				if (!this.bodyTable[0].rows.length)
					this.addRow();

				this.alignColumns();
				this._updateScroller();
				this._fixBottomBorder();
			} else {
				var self = this;
				
				this._gotoPage(this._getCurrentPage(), {
					data: {'phpr_delete_row': row[0].rowDataIndex}, 
					'onComplete': function(){
						if (!self.bodyTable[0].rows.length)
							self.addRow();
						
						if (onComplete !== undefined)
							onComplete();
					}
				});
			}
		},

		_bindSearch: function() {
			this.searchField = $('#' + this.element.attr('id') + '_search_field')
			if (this.searchField.length) {
				var self = this;
				new InputChangeTracker(this.searchField.attr('id'),  {regexp_mask: '^.*$'}).addEvent('change', function(){
					self._updateSearch();
				});
				this.searchField.bind('keydown', function(ev){
					if (ev.keyCode == 13)
						self._updateSearch();
				})
			}
		},
		
		_hideEditors: function() {
			this.element.trigger('hideEditors');
		},
		
		_scrollToCell: function(cell) {
			var scroller = this.element.find('.ui-grid-h-scroll');
			if (scroller.length) {
				cellPosition = cell.position();
				if (cellPosition.left < 0) {
					var tweak  = cell.is(':first-child') ? -1 : 0;
					scroller.scrollLeft(scroller.scrollLeft() + cellPosition.left + tweak);
				} else {
					var offsetRight = scroller.width() - (cellPosition.left + cell.width());
					
					if (offsetRight < 0)
						scroller.scrollLeft(scroller.scrollLeft() - offsetRight);
				}
			}
		},
		
		_pauseScrolling: function() {
			this._scrollingPaused = true;
			this.element.find('.ui-grid-h-scroll').addClass('no-scroll');
		},

		_resumeScrolling: function() {
			this._scrollingPaused = false;
			this.element.find('.ui-grid-h-scroll').removeClass('no-scroll');
		},
		
		_disableRowAdding: function() {
			this.disableRowAdding = true;
		},

		_enableRowAdding: function() {
			this.disableRowAdding = false;
		},
		
		/*
		 * Row menus
		 */
		
		_rowMouseEnter: function(event){
			var self = this;

			$(event.currentTarget).find('span.ui-grid-menu').removeClass('menu-visible');

			this._clearMenuTimer();
			this.menuTimer = window.setInterval(function(){
				self._displayRowMenu(event);
			}, 200);
		},
		
		_displayRowMenu: function(event) {
			this._clearMenuTimer();

			var row = $(event.currentTarget);
				
			if (row.hasClass('ui-no-menu'))
				return;
				
			var menu = row.find('span.ui-grid-menu');
			if (!menu.length)
				return;
				
			menu.css('left', 'auto');

			var rowHeight = row.outerHeight(),
				scroller = this.element.find('.ui-grid-h-scroll');
			if (scroller.length) {
				var cell = row.find('td:last'),
					scrollerWidth = scroller.width(),
					cellWidth = cell.width(),
					fixMenuPosition = function() {
						var cellPosition = cell.position(),
							cellOffset = cellPosition.left - scrollerWidth;

						if ((cellOffset + cellWidth) > 0)
							menu.css('left', (-1*cellOffset - 21) + 'px');
						else
							menu.css('left', 'auto');
					};
				
				scroller.bind('scroll.grid', fixMenuPosition);
				fixMenuPosition();
			}

			menu.css('height', rowHeight-1);
			menu.addClass('menu-visible');
			this.currentMenu = menu;
		},
		
		_rowMouseLeave: function(event) {
			this._clearMenuTimer();
		},

		_hideRowMenu: function(event) {
			this._clearMenuTimer();

			if (!this.currentMenu)
				return;
				
			var scroller = this.element.find('.ui-grid-h-scroll');
			scroller.unbind('.grid');

			this.currentMenu.removeClass('menu-visible');
			this.currentMenu = null;
		},
		
		_disableRowMenu: function(cell) {
			this._clearMenuTimer();
			this._hideRowMenu();
			$(cell).closest('tr').addClass('ui-no-menu');
		},
		
		_enableRowMenu: function(cell) {
			$(cell).closest('tr').removeClass('ui-no-menu');
		},
		
		_clearMenuTimer: function() {
			if (this.menuTimer) {
				window.clearInterval(this.menuTimer);
				this.menuTimer = null;
			}
		},

		/*
		 * Drag support
		 */
		
		_dragStart: function(event) {
			if (event.target.tagName == 'INPUT' || event.target.tagName == 'SELECT')
				return;

			this.dragging = true; 
			this.dragStartOffset = event.pageY;
			this.dragPrevOffset = 0;
			this.dragRow = event.currentTarget;
			this.dragStarted = false;
			this.bodyTable.disableSelection();

			if (this.options.scrollable) {
				this.viewportOffset = this.viewport.offset();
				this.viewportHeight = this.viewport.height();
				this.overviewHeight = this.overview.height();
			}
		},
		
		_mouseMove: function(event) {
			if (!this.dragging)
				return;
				
			if (!this.dragStarted) {
				this.element.trigger('hideEditors');
				this.dragStarted = true;
				this.bodyTable.addClass('drag');
				$(this.dragRow).addClass('ui-selected');
			}
			
			if (this.options.scrollable) {
				if (this.viewportOffset.top > event.pageY) {
					// var scrollOffset = Math.max(this.scrollablePanel.scrollTop() - this.viewportOffset.top + event.pageY, 0);
					this.scroller.scrollbar('setPosition', this.scrollablePanel.scrollTop()-50);
				} else if (event.pageY > (this.viewportOffset.top + this.viewportHeight)) {
					// var 
					// 	bottom = this.viewportOffset.top + this.viewportHeight,
					// 	scrollOffset = Math.min(this.scrollablePanel.scrollTop() + event.pageY - bottom, this.overviewHeight-this.viewportHeight);
					this.scroller.scrollbar('setPosition', this.scrollablePanel.scrollTop()+50);
				}
			}

			var offset = event.pageY - this.dragStartOffset;
			if (offset != this.dragPrevOffset) {
				var movingDown = offset > this.dragPrevOffset;
				this.dragPrevOffset = offset;

				var currentRow = this._findDragHoverRow(this.dragRow, event.pageY);
				if (currentRow !== null && currentRow !== undefined) {
					if (movingDown)
						this.dragRow.parentNode.insertBefore(this.dragRow, currentRow.nextSibling);
					else
						this.dragRow.parentNode.insertBefore(this.dragRow, currentRow);
				}
			}
			
			this.alignColumns();
		},
		
		_findDragHoverRow: function(currentRow, y) {
			var rows = this.bodyTable[0].rows;
			for (var i=0; i<rows.length; i++) {
				var 
					row = rows[i],
					rowTop = $(row).offset().top,
					rowHeight = row.offsetHeight;

				if ((y > rowTop) && (y < (rowTop + rowHeight))) {
					if (row == currentRow)
						return null;

					return row;
				}
			}
		},

		_mouseUp: function(event) {
			if (this.dragTimeout)
				window.clearTimeout(this.dragTimeout);
			
			if (!this.dragging)
				return;

			this.bodyTable.enableSelection();
			this.bodyTable.removeClass('drag');
			this.dragging = false;
			$(this.dragRow).removeClass('ui-selected');
			
			event.stopImmediatePropagation();
		},
		
		/*
		 * Navigation
		 */
			
		_getColumnEditor: function(cellIndex) {
			if (this.editors === undefined)
				this.editors = [];
				
			if (this.editors[cellIndex] !== undefined)
				return this.editors[cellIndex];

			var 
				columConfiguration = this.options.columns[cellIndex],
				editorName = columConfiguration.type + 'Editor';

			return this.editors[editorName] = new $.ui.grid.editors[editorName]();
		},
		
		_navigateRight: function(fromCell, selectAll) {
			selectAll = selectAll === undefined ? false : selectAll;

			var next = fromCell.nextAll('td.ui-grid-cell-navigatable');
			if (next.length) {
				$(next[0]).trigger('navigateTo', ['right', selectAll]);
				return true;
			}
			else {
				var 
					self = this,
					row = fromCell.parent();
					
				this._createFindNextRow(row, function(nextRow){
					if (!nextRow.length)
						return false;
				
					self._initRow(nextRow);

					var cell = nextRow.find('td.ui-grid-cell-navigatable:first');
					cell.trigger('navigateTo', ['right', selectAll]);
				});
				
				return true;
			}
				
			return false;
		},
		
		_navigateLeft: function(fromCell, selectAll) {
			selectAll = selectAll === undefined ? false : selectAll;

			var prev = fromCell.prevAll('td.ui-grid-cell-navigatable');
			if (prev.length) {
				$(prev[0]).trigger('navigateTo', ['left', selectAll]);
				return true;
			}
			else {
				var row = fromCell.closest('tr');
				
				this._createFindPrevRow(row, function(prevRow){
					if (!prevRow.length)
						return;

					self._initRow(prevRow);
					var cell = prevRow.find('td.ui-grid-cell-navigatable:last');
					cell.trigger('navigateTo', ['left', selectAll]);
				});

				return true;
			}
				
			return false;
		},
		
		_navigateDown: function(fromCell) {
			var 
				self = this,
				row = fromCell.parent(),
				index = row.children('td').index(fromCell);

			this._createFindNextRow(row, function(nextRow){
				if (!nextRow.length)
					return;

				self._initRow(nextRow);
				nextRow.find('td:eq('+index+')').trigger('navigateTo', ['left']);
			});
		},

		_navigateUp: function(fromCell) {
			var 
				self = this,
				row = fromCell.parent(),
				index = row.children('td').index(fromCell);
			
			this._createFindPrevRow(row, function(prevRow){
				if (!prevRow.length)
					return;

				self._initRow(prevRow);
				prevRow.find('td:eq('+index+')').trigger('navigateTo', ['left']);
			});
		},
		
		_setCurrentRow: function(row) {
			if (this.currentRow !== undefined && this.currentRow != row)
				this.currentRow.removeClass('current-row');

			this.currentRow = row;
			this.currentRow.addClass('current-row');
		},
		
		_createFindNextRow: function(currentRow, callback) {
			this._hideRowMenu();
			
			if (!currentRow.is(':last-child'))
				return callback(currentRow.next('tr'));

			if (!this.options.useDataSource || this.bodyTable.find('tr').length < this.options.pageSize)
				return callback(this.addRow());

			var self = this;
				
			/*
			 * If the row is last on this page, but the page is not last,
			 * go to the first row of the next page.
			 */
			if (!this._isLastPage()) {
				this._gotoPage(this._getCurrentPage() + 1, {onComplete: function(){
					var row = self.bodyTable.find('tr:first');
					if (row.length)
						callback(row);
				}});
			} else {
				/*
				 * If the row is last on this page, and the page is last,
				 * create new record in the data source and go to the next page.
				 */
				
				this.rowsAdded = this.rowsAdded === undefined ? 1 : this.rowsAdded+1;
				
				this._gotoPage(this._getCurrentPage() + 1, {data: {'phpr_append_row': 1, 'phpr_new_row_key': -1*this.rowsAdded}, onComplete: function(){
					var row = self.bodyTable.find('tr:first');
					if (row.length)
						callback(row);
				}});
			}
		},
		
		_createFindPrevRow: function(currentRow, callback) {
			this._hideRowMenu();
			
			if (!currentRow.is(':first-child'))
				return callback(currentRow.prev('tr'));

			if (!this.options.useDataSource)
				return;
			
			/*
			 * Go to the previous page
			 */
			
			var self = this;
			
			if (!this._isFirstPage()) {
				this._gotoPage(this._getCurrentPage() - 1, {onComplete: function(){
					var row = self.bodyTable.find('tr:last');
					if (row.length)
						callback(row);
				}});
			}
		},
		
		_createRowInTheEnd: function(callback) {
			this.rowsAdded = this.rowsAdded === undefined ? 1 : this.rowsAdded+1;
			
			if (this._isLastPage() && this.bodyTable.find('tr').length < this.options.pageSize)
				return callback(this.addRow());
			
			this._gotoPage('last', {data: {'phpr_append_row': 1, 'phpr_new_row_key': -1*this.rowsAdded}, onComplete: function(){
				var row = self.bodyTable.find('tr:last');
				if (row.length)
					callback(row);
			}});
		},
		
		_isLastPage: function() {
			return this.element.find('.grid-is-last-page').val() === '1';
		},
		
		_isFirstPage: function() {
			return this.element.find('.grid-is-first-page').val() === '1';
		},
		
		_getCurrentPage: function() {
			return parseInt(this.element.find('.grid-current-page').val());
		},
		
		_getTotalRowCount: function() {
			return parseInt(this.element.find('.grid-total-record-count').val());
		},
		
		/*
		 * Server response handling
		 */
		
		_handleWidgetResponseData: function(data) {
			if (this.element.parent().length == 0)
				return;
			
			if (data.widget === undefined || data.widget != 'grid')
				return;

			if (this.options.name != data.name)
				return;
				
			var 
				self = this;
				handleError = function() {
					errorFieldName = self.options.name+'['+data.row+']['+data.column+']',
					errorField = self.bodyTable.find('input.cell-value[name="'+errorFieldName+'"]');

					if (!errorField.length)
						return;

					self._markErrorField(errorField);
					self._scrollToCell(errorField.closest('td'));
				}
			
			var searchEnabled = false;
			if (this.searchField !== undefined) {
				if (this.searchField.val().length) {
					this.searchField.val('');
					searchEnabled = true;
				}
			}
				
			if (this.options.useDataSource && (searchEnabled || (data.page_index !== undefined && this._getCurrentPage() != data.page_index))) {
				this._gotoPage(data.page_index, {onComplete: function(){
					handleError();
				}});
			} else
				handleError();
		},
		
		_markErrorField: function(field) {
			if (this._errorRow !== undefined)
			{
				this._errorRow.removeClass('grid-error');
				this._errorCell.removeClass('grid-error');
			}

			this._errorRow = field.closest('tr');
			this._errorCell = field.closest('td');
			
			this._errorRow.addClass('grid-error');
			this._errorCell.addClass('grid-error');

			if (this.options.scrollable)
			{
				var offset = this._errorCell.position().top - this._errorCell.height() + this.scrollablePanel.scrollTop();
				if (offset < 0)
					offset = 0;

				this.scroller.scrollbar('setPosition', offset);
			}
			
			this._errorCell.trigger('navigateTo');
		},
		
		/*
		 * Datasource support
		 */
		
		_gotoPage: function(pageIndex, options) {
			var self = this;
			
			if (options === undefined)
				options = {};
				
			if (options.data === undefined)
				options.data = {};

			this._disableRowMenu();
			
			var searchUpdatedRows = this.searchUpdatedRows !== undefined ? this.searchUpdatedRows : [];
			
			if (options.data.phpr_new_row_key !== undefined)
				searchUpdatedRows.push(options.data.phpr_new_row_key);

			if (this.rowsAdded === undefined)
				this.rowsAdded = 1;
			
			var searchTerm = this.searchField !== undefined ? this.searchField.val() : null,
				data = $.extend(true, {
					phpr_custom_event_name: 'on_navigate_to_page',
					phpr_event_field: this.options.dataFieldName,
					phpr_page_index: pageIndex,
					phpr_grid_search: searchTerm,
					phpr_grid_records_added: this.rowsAdded,
					phpr_grid_search_updated_records: searchUpdatedRows.join(',')
				}, options.data);

			this.element.phpr().post(this.actionName+'on_form_widget_event', {
				data: data,
				update: 'multi',
				customIndicator: LightLoadingIndicator,
				afterUpdate: function() {
					self.rebuild();
					self._dropHeaderCheckboxes();
					if (options.onComplete !== undefined)
						options.onComplete();
						
					self._enableRowMenu();
					
					if (!self._hasRows() && self.searchField !== undefined && self.searchField.val().length)
						self._showNoSearchResults();
					else
						self._hideNoSearchResults();
						
					self.rowsAdded += self._getTotalRowCount();
					
					var message = self.element.find('.grid-message-text').val();
					if (message.length)
						alert(message);
				},
				error: function(requestObj) {
					alert(requestObj.errorMessage);
				}
			}).send();
			
			return false;
		},
		
		updateData: function(serverEvent) {
			if (this.searchField !== undefined)
				this.searchField.val('');
			
			this.searchUpdatedRows = [];
			this._gotoPage(0, {data: {'phpr_grid_event': serverEvent}});
		},
		
		/*
		 * Search support
		 */
		
		_updateSearch: function() {
			this.searchUpdatedRows = [];
			
			this._gotoPage(0);
		},
		
		_markRowSearchUpdated: function(recordIndex) {
			if (this.searchUpdatedRows == undefined)
				this.searchUpdatedRows = [];
				
			this.searchUpdatedRows.push(recordIndex);
		},
		
		_showNoSearchResults: function() {
			this.noSearchResultsVisible = true;
			
			if (this.noSearchResultsMessage === undefined) {
				this.noSearchResultsMessage = $('<p class="ui-no-records noData"></p>').text('No records found');
				this.container.append(this.noSearchResultsMessage);
				this.noSearchResultsMessage.css('top', Math.round(this.container.height()/2-19));
				this.noSearchResultsMessage.css('left', Math.round(this.container.width()/2-100));
			}
			
			this.noSearchResultsMessage.css('display', 'block');
			this.existingTable.css('display', 'none');
		},
		
		_hideNoSearchResults: function() {
			this.noSearchResultsVisible = false;
			
			if (this.noSearchResultsMessage === undefined)
				return;

			this.noSearchResultsMessage.css('display', 'none');
			this.existingTable.css('display', 'table');
		},
		
		_isNoSearchResults: function() {
			return this.noSearchResultsVisible;
		},
		
		/*
		 * Header checkboxes support
		 */
		
		_initHeaderCheckboxes: function() {
			var self = this;
			this.headTable.find('th.checkbox-cell input').each(function(index, input) {
				input.addEvent('click', function() {
					var headCell = $(input).closest('th');
					var cellIndex = headCell.parent().children('th').index(headCell);
					var columnInfo = self.options.columns[cellIndex];

					self.bodyTable.find('tr').find('td:eq('+cellIndex+')').each(function(rowIndex, cell){
						if (input.checked)
							$(cell).find('div.checkbox').addClass('checked');
						else
							$(cell).find('div.checkbox').removeClass('checked');
							
						$(cell).find('.cell-value').val(input.checked ? 1 : 0);
						
						if (columnInfo.checked_class !== undefined) {
							var row = $(cell).parent();
							if (input.checked)
								row.addClass(columnInfo.checked_class);
							else
								row.removeClass(columnInfo.checked_class);
						}
					});
				})
			})
		},
		
		_dropHeaderCheckboxes: function() {
			this.headTable.find('th.checkbox-cell input').each(function(index, input) {
				input.cb_uncheck();
			});
		},
		
		/*
		 * Tracing
		 */

		_trace: function(message) {
			if (window.console)
				console.log(message);
			
			return;
		}
	});
})(jQuery);