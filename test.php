<?php
$hash = '$2y$10$Xk9vQ2mP8sL1nR4tY6uW7eH3jK5mN8pQ1rS2tU3vW4xY5zA6bC7d'; // paste yours
var_dump(password_verify('admin123', $hash));
?>