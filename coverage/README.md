About:
======
When you do blackboxtesting with selenium or even by hand, you might want to look which parts of code you executed. This script will save with
each request all covered codepathes and in the end a script can merge them and transform them into another format.


Installation:
=============
There is an example.index.php inside the project.. you'll need to wrap it around your index.php
Make sure that it can create or use a writable directory somewhere.
Also you might need to change the .htaccess to temporaryily point to this new index wrapper.

Then you just need to do the blackboxtesting.
In the end you can run the example.merger.php to get the desired format.
