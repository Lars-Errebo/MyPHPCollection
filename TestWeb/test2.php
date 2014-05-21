<?php
session_start();
?>
<html>
  <head><title>iframe session test</title></head>
  <body>
    <div>
      <?php
      if ( isset($_SESSION['productcheck']) && is_array($_SESSION['productcheck']) ) {
        foreach( $_SESSION['productcheck'] as $pc ) {
          echo $pc, "<br />\n";
        }
      }
      ?>
    </div>
  </body>
</html>




