const { ButtonBlockAppender } = wp.blockEditor;
const { useSelect } = wp.data;

wp.hooks.addFilter(
    'editor.BlockEdit',
    'block-editor-for-playground-blueprint/default-block-appender',
    (BlockEdit) => (props) => {

        const lastBlockClientId = useSelect((select) => {
            const blockOrder = select('core/block-editor').getBlockOrder();
            return blockOrder[blockOrder.length - 1]; // Get the last block's clientId
        }, []);

        return (
            <>
                <BlockEdit {...props} />
                {props.clientId === lastBlockClientId && (
                    <div className={'block-list-appender wp-block'}>
                        <ButtonBlockAppender
                            rootClientId={null} // Since we don't have nested blocks
                        />
                    </div>
                )}
            </>
        );
    }
);
