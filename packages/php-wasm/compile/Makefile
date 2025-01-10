base-image: base-image/.ready
base-image/.ready:
	cd base-image; docker build -f ./Dockerfile -t playground-php-wasm:base .

libz_asyncify: libz/asyncify/dist/root/lib/lib/libz.a
libz/asyncify/dist/root/lib/lib/libz.a: base-image
	docker build -f ./libz/Dockerfile -t playground-php-wasm:libz ./
	mkdir -p ./libz/asyncify/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libz):/root/lib/lib ./libz/asyncify/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libz):/root/lib/include ./libz/asyncify/dist/root/lib

libz_jspi: libz/jspi/dist/root/lib/lib/libz.a
libz/jspi/dist/root/lib/lib/libz.a: base-image
	docker build -f ./libz/Dockerfile -t playground-php-wasm:libz ./ --build-arg JSPI=1
	mkdir -p ./libz/jspi/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libz):/root/lib/lib ./libz/jspi/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libz):/root/lib/include ./libz/jspi/dist/root/lib

libzip_asyncify: libzip-1.2.0_asyncify libzip-1.9.2_asyncify

libzip-1.2.0_asyncify: libzip/asyncify/dist/1.2.0/root/lib/lib/libzip.a
libzip/asyncify/dist/1.2.0/root/lib/lib/libzip.a: base-image libz_asyncify
	mkdir -p ./libzip/asyncify/dist/1.2.0/root/lib
	docker build -f ./libzip/Dockerfile -t playground-php-wasm:libzip . --build-arg LIBZIP_VERSION=1.2.0 --progress=plain
	docker cp $$(docker create playground-php-wasm:libzip):/root/install/lib ./libzip/asyncify/dist/1.2.0/root/lib
	docker cp $$(docker create playground-php-wasm:libzip):/root/install/include ./libzip/asyncify/dist/1.2.0/root/lib

libzip-1.9.2_asyncify: libzip/asyncify/dist/1.9.2/root/lib/lib/libzip.a
libzip/asyncify/dist/1.9.2/root/lib/lib/libzip.a: base-image libz_asyncify
	mkdir -p ./libzip/asyncify/dist/1.9.2/root/lib
	docker build -f ./libzip/Dockerfile -t playground-php-wasm:libzip . --build-arg LIBZIP_VERSION=1.9.2 --progress=plain
	docker cp $$(docker create playground-php-wasm:libzip):/root/install/lib ./libzip/asyncify/dist/1.9.2/root/lib
	docker cp $$(docker create playground-php-wasm:libzip):/root/install/include ./libzip/asyncify/dist/1.9.2/root/lib

libzip_jspi: libzip-1.2.0_jspi libzip-1.9.2_jspi

libzip-1.2.0_jspi: libzip/jspi/dist/1.2.0/root/lib/lib/libzip.a
libzip/jspi/dist/1.2.0/root/lib/lib/libzip.a: base-image libz_jspi
	mkdir -p ./libzip/jspi/dist/1.2.0/root/lib
	docker build -f ./libzip/Dockerfile -t playground-php-wasm:libzip . --build-arg LIBZIP_VERSION=1.2.0 --build-arg JSPI=1 --progress=plain
	docker cp $$(docker create playground-php-wasm:libzip):/root/install/lib ./libzip/jspi/dist/1.2.0/root/lib
	docker cp $$(docker create playground-php-wasm:libzip):/root/install/include ./libzip/jspi/dist/1.2.0/root/lib

libzip-1.9.2_jspi: libzip/jspi/dist/1.9.2/root/lib/lib/libzip.a
libzip/jspi/dist/1.9.2/root/lib/lib/libzip.a: base-image libz_jspi
	mkdir -p ./libzip/jspi/dist/1.9.2/root/lib
	docker build -f ./libzip/Dockerfile -t playground-php-wasm:libzip . --build-arg LIBZIP_VERSION=1.9.2 --build-arg JSPI=1 --progress=plain
	docker cp $$(docker create playground-php-wasm:libzip):/root/install/lib ./libzip/jspi/dist/1.9.2/root/lib
	docker cp $$(docker create playground-php-wasm:libzip):/root/install/include ./libzip/jspi/dist/1.9.2/root/lib

libpng16_asyncify: libpng16/asyncify/dist/root/lib/lib/libpng16.a
libpng16/asyncify/dist/root/lib/lib/libpng16.a: base-image libz_asyncify
	mkdir -p ./libpng16/asyncify/dist/root/lib
	docker build -f ./libpng16/Dockerfile -t playground-php-wasm:libpng .
	docker cp $$(docker create playground-php-wasm:libpng):/root/install/lib ./libpng16/asyncify/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libpng):/root/install/include ./libpng16/asyncify/dist/root/lib

libpng16_jspi: libpng16/jspi/dist/root/lib/lib/libpng16.a
libpng16/jspi/dist/root/lib/lib/libpng16.a: base-image libz_jspi
	mkdir -p ./libpng16/jspi/dist/root/lib
	docker build -f ./libpng16/Dockerfile -t playground-php-wasm:libpng . --build-arg JSPI=1
	docker cp $$(docker create playground-php-wasm:libpng):/root/install/lib ./libpng16/jspi/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libpng):/root/install/include ./libpng16/jspi/dist/root/lib

libjpeg_asyncify: libjpeg/asyncify/dist/root/lib/lib/libjpeg.a
libjpeg/asyncify/dist/root/lib/lib/libjpeg.a: base-image libz_asyncify
	mkdir -p ./libjpeg/asyncify/dist/root/lib
	docker build -f ./libjpeg/Dockerfile -t playground-php-wasm:libpng .
	docker cp $$(docker create playground-php-wasm:libpng):/root/lib/lib32 ./libjpeg/asyncify/dist/root/lib
	# The directory might exist if another build step failed and we're retrying now
	mv ./libjpeg/asyncify/dist/root/lib/lib32 ./libjpeg/asyncify/dist/root/lib/lib || true
	docker cp $$(docker create playground-php-wasm:libpng):/root/lib/include ./libjpeg/asyncify/dist/root/lib

libjpeg_jspi: libjpeg/jspi/dist/root/lib/lib/libjpeg.a
libjpeg/jspi/dist/root/lib/lib/libjpeg.a: base-image libz_jspi
	mkdir -p ./libjpeg/jspi/dist/root/lib
	docker build -f ./libjpeg/Dockerfile -t playground-php-wasm:libpng . --build-arg JSPI=1
	docker cp $$(docker create playground-php-wasm:libpng):/root/lib/lib32 ./libjpeg/jspi/dist/root/lib
	# The directory might exist if another build step failed and we're retrying now
	mv ./libjpeg/jspi/dist/root/lib/lib32 ./libjpeg/jspi/dist/root/lib/lib || true
	docker cp $$(docker create playground-php-wasm:libpng):/root/lib/include ./libjpeg/jspi/dist/root/lib

libwebp_asyncify: libwebp/asyncify/dist/root/lib/lib/libwebp.a
libwebp/asyncify/dist/root/lib/lib/libwebp.a: base-image libz_asyncify
	mkdir -p ./libwebp/asyncify/dist/root/lib
	docker build -f ./libwebp/Dockerfile -t playground-php-wasm:libpng .
	docker cp $$(docker create playground-php-wasm:libpng):/root/lib/lib ./libwebp/asyncify/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libpng):/root/lib/include ./libwebp/asyncify/dist/root/lib

libwebp_jspi: libwebp/jspi/dist/root/lib/lib/libwebp.a
libwebp/jspi/dist/root/lib/lib/libwebp.a: base-image libz_jspi
	mkdir -p ./libwebp/jspi/dist/root/lib
	docker build -f ./libwebp/Dockerfile -t playground-php-wasm:libpng . --build-arg JSPI=1
	docker cp $$(docker create playground-php-wasm:libpng):/root/lib/lib ./libwebp/jspi/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libpng):/root/lib/include ./libwebp/jspi/dist/root/lib

libxml2_asyncify: libxml2/asyncify/dist/root/lib/lib/libxml2.a
libxml2/asyncify/dist/root/lib/lib/libxml2.a: base-image libz_asyncify
	mkdir -p ./libxml2/asyncify/dist/root/lib
	docker build -f ./libxml2/Dockerfile -t playground-php-wasm:libxml2 .
	docker cp $$(docker create playground-php-wasm:libxml2):/root/lib/bin ./libxml2/asyncify/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libxml2):/root/lib/lib ./libxml2/asyncify/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libxml2):/root/lib/include ./libxml2/asyncify/dist/root/lib

libxml2_jspi: libxml2/jspi/dist/root/lib/lib/libxml2.a
libxml2/jspi/dist/root/lib/lib/libxml2.a: base-image libz_jspi
	mkdir -p ./libxml2/jspi/dist/root/lib
	docker build -f ./libxml2/Dockerfile -t playground-php-wasm:libxml2 . --build-arg JSPI=1
	docker cp $$(docker create playground-php-wasm:libxml2):/root/lib/bin ./libxml2/jspi/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libxml2):/root/lib/lib ./libxml2/jspi/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libxml2):/root/lib/include ./libxml2/jspi/dist/root/lib

libopenssl_asyncify: libopenssl/asyncify/dist/1.1.0h/root/lib/lib/libssl.a libopenssl/asyncify/dist/1.1.1/root/lib/lib/libssl.a

libopenssl/asyncify/dist/1.1.0h/root/lib/lib/libssl.a: base-image libz_asyncify
	mkdir -p ./libopenssl/asyncify/dist/root/lib
	docker build -f ./libopenssl/Dockerfile -t playground-php-wasm:libopenssl-1.1.0h . --build-arg OPENSSL_VERSION=1.1.0h
	mkdir -p ./libopenssl/asyncify/dist/1.1.0h/root/lib
	docker cp $$(docker create playground-php-wasm:libopenssl-1.1.0h):/root/install/lib ./libopenssl/asyncify/dist/1.1.0h/root/lib
	docker cp $$(docker create playground-php-wasm:libopenssl-1.1.0h):/root/install/include ./libopenssl/asyncify/dist/1.1.0h/root/lib

libopenssl/asyncify/dist/1.1.1/root/lib/lib/libssl.a: base-image libz_asyncify
	mkdir -p ./libopenssl/asyncify/dist/root/lib
	docker build -f ./libopenssl/Dockerfile -t playground-php-wasm:libopenssl-1.1.1 . --build-arg OPENSSL_VERSION=1.1.1t
	mkdir -p ./libopenssl/asyncify/dist/1.1.1/root/lib
	docker cp $$(docker create playground-php-wasm:libopenssl-1.1.1):/root/install/lib ./libopenssl/asyncify/dist/1.1.1/root/lib
	docker cp $$(docker create playground-php-wasm:libopenssl-1.1.1):/root/install/include ./libopenssl/asyncify/dist/1.1.1/root/lib

libopenssl_jspi: libopenssl/jspi/dist/1.1.0h/root/lib/lib/libssl.a libopenssl/jspi/dist/1.1.1/root/lib/lib/libssl.a

libopenssl/jspi/dist/1.1.0h/root/lib/lib/libssl.a: base-image libz_jspi
	mkdir -p ./libopenssl/jspi/dist/root/lib
	docker build -f ./libopenssl/Dockerfile -t playground-php-wasm:libopenssl-1.1.0h . --build-arg JSPI=1 --build-arg OPENSSL_VERSION=1.1.0h
	mkdir -p ./libopenssl/jspi/dist/1.1.0h/root/lib
	docker cp $$(docker create playground-php-wasm:libopenssl-1.1.0h):/root/install/lib ./libopenssl/jspi/dist/1.1.0h/root/lib
	docker cp $$(docker create playground-php-wasm:libopenssl-1.1.0h):/root/install/include ./libopenssl/jspi/dist/1.1.0h/root/lib

libopenssl/jspi/dist/1.1.1/root/lib/lib/libssl.a: base-image libz_jspi
	mkdir -p ./libopenssl/jspi/dist/root/lib
	docker build -f ./libopenssl/Dockerfile -t playground-php-wasm:libopenssl-1.1.1 . --build-arg JSPI=1 --build-arg OPENSSL_VERSION=1.1.1t
	mkdir -p ./libopenssl/jspi/dist/1.1.1/root/lib
	docker cp $$(docker create playground-php-wasm:libopenssl-1.1.1):/root/install/lib ./libopenssl/jspi/dist/1.1.1/root/lib
	docker cp $$(docker create playground-php-wasm:libopenssl-1.1.1):/root/install/include ./libopenssl/jspi/dist/1.1.1/root/lib

libsqlite3_asyncify: libsqlite3/asyncify/dist/root/lib/lib/libsqlite3.a
libsqlite3/asyncify/dist/root/lib/lib/libsqlite3.a: base-image libz_asyncify
	mkdir -p ./libsqlite3/asyncify/dist/root/lib
	docker build -f ./libsqlite3/Dockerfile -t playground-php-wasm:libsqlite3 .
	docker cp $$(docker create playground-php-wasm:libsqlite3):/root/lib/lib ./libsqlite3/asyncify/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libsqlite3):/root/lib/include ./libsqlite3/asyncify/dist/root/lib

libsqlite3_jspi: libsqlite3/jspi/dist/root/lib/lib/libsqlite3.a
libsqlite3/jspi/dist/root/lib/lib/libsqlite3.a: base-image libz_jspi
	mkdir -p ./libsqlite3/jspi/dist/root/lib
	docker build -f ./libsqlite3/Dockerfile -t playground-php-wasm:libsqlite3 . --build-arg JSPI=1
	docker cp $$(docker create playground-php-wasm:libsqlite3):/root/lib/lib ./libsqlite3/jspi/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libsqlite3):/root/lib/include ./libsqlite3/jspi/dist/root/lib

libiconv_asyncify: libiconv/asyncify/dist/root/lib/lib/libiconv.a
libiconv/asyncify/dist/root/lib/lib/libiconv.a: base-image libz_asyncify
	mkdir -p ./libiconv/asyncify/dist/root/lib
	docker build -f ./libiconv/Dockerfile -t playground-php-wasm:libiconv .
	docker cp $$(docker create playground-php-wasm:libiconv):/root/install/lib ./libiconv/asyncify/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libiconv):/root/install/include ./libiconv/asyncify/dist/root/lib

libiconv_jspi: libiconv/jspi/dist/root/lib/lib/libiconv.a
libiconv/jspi/dist/root/lib/lib/libiconv.a: base-image libz_jspi
	mkdir -p ./libiconv/jspi/dist/root/lib
	docker build -f ./libiconv/Dockerfile -t playground-php-wasm:libiconv . --build-arg JSPI=1
	docker cp $$(docker create playground-php-wasm:libiconv):/root/install/lib ./libiconv/jspi/dist/root/lib
	docker cp $$(docker create playground-php-wasm:libiconv):/root/install/include ./libiconv/jspi/dist/root/lib

# This didn't get a proper JSPI port because it wasn't actually used in the build.
# It's kept here because we still will want PHP WASM to support interactive CLI and
# a lot of work has been done to get the libncurses and libedit support where it is now.
# libncurses_asyncify: libncurses/asyncify/dist/root/lib/lib/libncurses.a
# libncurses/asyncify/dist/root/lib/lib/libncurses.a: base-image libz_asyncify
# 	mkdir -p ./libncurses/asyncify/dist/root/lib
# 	docker build -f ./libncurses/Dockerfile -t playground-php-wasm:libncurses .
# 	docker cp $$(docker create playground-php-wasm:libncurses):/root/install/lib ./libncurses/asyncify/dist/root/lib
# 	docker cp $$(docker create playground-php-wasm:libncurses):/root/install/include ./libncurses/asyncify/dist/root/lib

# libncurses_jspi: libncurses/jspi/dist/root/lib/lib/libncurses.a
# libncurses/jspi/dist/root/lib/lib/libncurses.a: base-image libz_jspi
# 	mkdir -p ./libncurses/jspi/dist/root/lib
# 	docker build -f ./libncurses/Dockerfile -t playground-php-wasm:libncurses . --build-arg JSPI=1
# 	docker cp $$(docker create playground-php-wasm:libncurses):/root/lib/lib ./libncurses/jspi/dist/root/lib
# 	docker cp $$(docker create playground-php-wasm:libncurses):/root/lib/include ./libncurses/jspi/dist/root/lib

# libedit_asyncify: libedit/asyncify/dist/root/lib/lib/libedit.a
# libedit/asyncify/dist/root/lib/lib/libedit.a: base-image libncurses_asyncify
# 	mkdir -p ./libedit/asyncify/dist/root/lib
# 	docker build -f ./libedit/Dockerfile -t playground-php-wasm:libedit .
# 	docker cp $$(docker create playground-php-wasm:libedit):/root/install/lib ./libedit/asyncify/dist/root/lib
# 	docker cp $$(docker create playground-php-wasm:libedit):/root/install/include ./libedit/asyncify/dist/root/lib

# libedit_jspi: libedit/jspi/dist/root/lib/lib/libedit.a
# libedit/jspi/dist/root/lib/lib/libedit.a: base-image libncurses_jspi
# 	mkdir -p ./libedit/jspi/dist/root/lib
# 	docker build -f ./libedit/Dockerfile -t playground-php-wasm:libedit . --build-arg JSPI=1
# 	docker cp $$(docker create playground-php-wasm:libedit):/root/install/lib ./libedit/jspi/dist/root/lib
# 	docker cp $$(docker create playground-php-wasm:libedit):/root/install/include ./libedit/jspi/dist/root/lib

bison2.7: bison2.7/dist/usr/local/bison/bin/bison
bison2.7/dist/usr/local/bison/bin/bison: base-image
	mkdir -p ./bison2.7/dist/usr/local/bison
	docker build -f ./bison2.7/Dockerfile -t playground-php-wasm:bison2.7 . --progress=plain
	docker cp $$(docker create playground-php-wasm:bison2.7):/usr/local/bison ./bison2.7/dist/usr/local

oniguruma_asyncify: oniguruma/asyncify/dist/root/lib/lib/libonig.a
oniguruma/asyncify/dist/root/lib/lib/libonig.a: base-image
	mkdir -p ./oniguruma/asyncify/dist/root/lib
	docker build -f ./oniguruma/Dockerfile -t playground-php-wasm:oniguruma . --progress=plain
	docker cp $$(docker create playground-php-wasm:oniguruma):/root/lib/lib ./oniguruma/asyncify/dist/root/lib/
	docker cp $$(docker create playground-php-wasm:oniguruma):/root/lib/include ./oniguruma/asyncify/dist/root/lib/

oniguruma_jspi: oniguruma/jspi/dist/root/lib/lib/libonig.a
oniguruma/jspi/dist/root/lib/lib/libonig.a: base-image
	mkdir -p ./oniguruma/jspi/dist/root/lib
	docker build -f ./oniguruma/Dockerfile -t playground-php-wasm:oniguruma . --progress=plain --build-arg JSPI=1
	docker cp $$(docker create playground-php-wasm:oniguruma):/root/lib/lib ./oniguruma/jspi/dist/root/lib/
	docker cp $$(docker create playground-php-wasm:oniguruma):/root/lib/include ./oniguruma/jspi/dist/root/lib/

libcurl_asyncify: libcurl/asyncify/dist/root/lib/lib/libcurl.a
libcurl/asyncify/dist/root/lib/lib/libcurl.a: base-image libz libopenssl
	mkdir -p ./libcurl/asyncify/dist/root/lib
	docker build -f ./libcurl/Dockerfile -t playground-php-wasm:libcurl . --progress=plain
	docker cp $$(docker create playground-php-wasm:libcurl):/root/curl-7.69.1/lib/.libs ./libcurl/asyncify/dist/root/lib/lib
	docker cp $$(docker create playground-php-wasm:libcurl):/root/curl-7.69.1/include/ ./libcurl/asyncify/dist/root/lib

libcurl_jspi: libcurl/jspi/dist/root/lib/lib/libcurl.a
libcurl/jspi/dist/root/lib/lib/libcurl.a: base-image libz libopenssl
	mkdir -p ./libcurl/jspi/dist/root/lib
	docker build -f ./libcurl/Dockerfile -t playground-php-wasm:libcurl . --progress=plain --build-arg JSPI=1
	docker cp $$(docker create playground-php-wasm:libcurl):/root/curl-7.69.1/lib/.libs ./libcurl/jspi/dist/root/lib/lib
	docker cp $$(docker create playground-php-wasm:libcurl):/root/curl-7.69.1/include/ ./libcurl/jspi/dist/root/lib

all: all_jspi all_asyncify
all_jspi: libz_jspi libzip_jspi libpng16_jspi libjpeg_jspi libwebp_jspi libxml2_jspi libopenssl_jspi libsqlite3_jspi libiconv_jspi bison2.7 oniguruma_jspi libcurl_jspi
all_asyncify: libz_asyncify libzip_asyncify libpng16_asyncify libjpeg_asyncify libwebp_asyncify libxml2_asyncify libopenssl_asyncify libsqlite3_asyncify libiconv_asyncify bison2.7 oniguruma_asyncify libcurl_asyncify

clean:
	rm -rf ./libz/jspi/dist
	rm -rf ./libz/asyncify/dist
	rm -rf ./libzip/jspi/dist
	rm -rf ./libzip/asyncify/dist
	rm -rf ./libpng16/jspi/dist
	rm -rf ./libpng16/asyncify/dist
	rm -rf ./libjpeg/jspi/dist
	rm -rf ./libjpeg/asyncify/dist
	rm -rf ./libwebp/jspi/dist
	rm -rf ./libwebp/asyncify/dist
	rm -rf ./libxml2/jspi/dist
	rm -rf ./libxml2/asyncify/dist
	rm -rf ./libopenssl/jspi/dist
	rm -rf ./libopenssl/asyncify/dist
	rm -rf ./libsqlite3/jspi/dist
	rm -rf ./libsqlite3/asyncify/dist
	rm -rf ./libiconv/jspi/dist
	rm -rf ./libiconv/asyncify/dist
	rm -rf ./bison2.7/dist
	rm -rf ./oniguruma/jspi/dist
	rm -rf ./oniguruma/asyncify/dist
