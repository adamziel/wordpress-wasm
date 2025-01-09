dnl config.m4 for extension mysqli_polyfill

PHP_ARG_ENABLE(mysqli_polyfill, whether to enable mysqli_polyfill support,
[  --enable-mysqli_polyfill   Enable mysqli_polyfill support])

if test "$PHP_MYSQLI_POLYFILL" != "no"; then
  PHP_NEW_EXTENSION(mysqli_polyfill, mysqli_polyfill.c, $ext_shared)
fi