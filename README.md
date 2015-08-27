This is a simple CMS to place one include and one line of code to allow anyone you give access to, the ability to edit your website.

1. Create a google Doc and make it publicly viewable (not publicly editable)
2. grab the id of the google doc
3. Include gdoccms.php on your web page
4. In the location where you want to give access to a user to modify the page, simply add this line of code:
> <?php google_edit('##id_of_google_doc'); ?>
   
That's it!

Please feel free to contribute more as this was created in roughly 20 minutes

