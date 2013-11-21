/**
 * How to select text in the grid (with the mouse) so that it can be copied to the clipboard
 * http://www.extjs.com/learn/Ext_FAQ_Grid#How_to_select_text_in_the_grid_.28with_the_mouse.29_so_that_it_can_be_copied_to_the_clipboard
 *
 */
if (!Ext.grid.GridView.prototype.templates) {
   Ext.grid.GridView.prototype.templates = {};
}
Ext.grid.GridView.prototype.templates.cell = new Ext.Template(
   '<td class="x-grid3-col x-grid3-cell x-grid3-td-{id} x-selectable {css}" style="{style}; cursor: text" tabIndex="0" {cellAttr}>',
		'<div class="x-grid3-cell-inner x-grid3-col-{id}" {attr}>{value}</div>',
   '</td>'
);