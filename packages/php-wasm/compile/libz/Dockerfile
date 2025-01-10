FROM playground-php-wasm:base

ARG JSPI

RUN wget https://www.zlib.net/fossils/zlib-1.2.13.tar.gz && \
    tar xf zlib-1.2.13.tar.gz && \
    mv zlib-1.2.13 /root/zlib
RUN chmod a+x /root/zlib/configure
RUN source /root/emsdk/emsdk_env.sh && \
    cd /root/zlib && \
    emconfigure ./configure --prefix=/root/lib && \
    # emmake make fails, but only after it builds the library files.
    # Let's just ignore the errors and proceed with the built libraries.
    export JSPI_FLAGS=$(if [ "$JSPI" = "1" ]; then echo "-sSUPPORT_LONGJMP=wasm -fwasm-exceptions"; else echo ""; fi) && \
    (EMCC_FLAGS="-sSIDE_MODULE $JSPI_FLAGS" emmake make || true) && \
    EMCC_FLAGS="-sSIDE_MODULE $JSPI_FLAGS" emmake make install
