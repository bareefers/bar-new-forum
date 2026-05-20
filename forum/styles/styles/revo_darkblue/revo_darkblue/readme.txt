XenForo style: Revo

Version: 1.1.0
Compatible with XenForo: 1.4.5

License: see license.txt


------------
Installation
------------

Upload contents of directory "styles" on your forum in directory "styles".

Log in to admin control panel, click "Styles", click "Import a Style", fill out the form:
- Import from uploaded file: put style-Revo.xml in that field
- Import as: Child of style: (no parent style)

Click "Import". Wait for XenForo to import style.

If you have purchased branding free option, follow last steps again for style-Branding-Free-Style.xml, but in "Child of style" field select "Revo" style.


------------------------------------
Additional installation instructions
------------------------------------

If you are considering customising or renaming style, do not edit "Revo" style. Create a child style instead. This will make updating style much easier.

How to create child style:

Log in to admin control panel, click "Styles", click "Create New Style" button, fill out the form:
- Parent style: Revo (or branding free child style if you have branding free option)
- Title: anything you want
- Description: anything you want (you can leave this empty)
- Allow user selection: checked

Click "Save Style".

Then on styles page set it as default style. To do that click radio button (rounded check box) next to your newly created style.

To prevent users from selecting other styles, uncheck boxes for all other styles.


--------------
Updating style
--------------

Upload contents of directory "style" on your forum. Overwrite existing files, except for files that you have customised. If you have customised JavaScript files, overwrite them and then redo your customisation.

Log in to admin control panel, click "Styles", click "Import a Style" button, fill out the form:
- Import from uploaded file: put style-Revo.xml in that field
- Import as: check "Overwrite style" box, select "Revo" from available styles list.

Click "Import". Wait for XenForo to import style.

