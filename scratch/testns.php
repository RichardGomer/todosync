<?php

require('testns.class.php');

class qux implements \foo\bar\baz {

};

print_r(class_implements('qux'));
