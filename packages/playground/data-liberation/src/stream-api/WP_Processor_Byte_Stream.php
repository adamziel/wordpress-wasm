<?php

class ProcessorByteStream extends WP_Byte_Stream
{
    public $processor;
    protected $generate_next_chunk_callback;

    public function __construct($processor, $generate_next_chunk_callback)
    {
        $this->processor = $processor;
        $this->generate_next_chunk_callback = $generate_next_chunk_callback;
        parent::__construct($generate_next_chunk_callback);
    }

    public function pause()
    {
        return [
            'processor' => $this->processor->pause(),
            'output_bytes' => $this->state->output_bytes,
        ];
    }

    public function resume($paused_state)
    {
        $this->processor->resume($paused_state['processor']);
        $this->state->output_bytes = $paused_state['output_bytes'];
    }    
    
    protected function generate_next_chunk(): bool {
        return ($this->generate_next_chunk_callback)($this->state);
    }

}
