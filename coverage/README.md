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

Bugs:
=====
There are 1 problem to read correctly your xml in phpstorm. IDE cannot resolve the path correctly.
It's necessary to insert two parameters in the function toClover PathRootServer and PathRootLocal. So the function can fix the path of each
file by the good local path for the IDE.

And just a remark, XDebug gives also the number -2 and -1 for the lines. You record only the lines >0. I think, the tool will be better, if
it saves all these data in the final xml.
