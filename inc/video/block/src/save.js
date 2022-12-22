/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-block-editor/#useBlockProps
 */
import {useBlockProps} from '@wordpress/block-editor';

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#save
 *
 * @param {Object} props            Properties passed to the function.
 * @param {Object} props.attributes Available block attributes.
 * @return {WPElement} Element to render.
 */
export default function save({attributes}) {
  const blockProps = useBlockProps.save();
  if (attributes.video_id) {
    return (
      <figure class="wp-embed-aspect-16-9 wp-has-aspect-ratio wp-block-embed is-type-video">
        <div class="wp-block-embed__wrapper">
          <iframe src={`https://iframe.mediadelivery.net/embed/${IUP_VIDEO.libraryId}/${attributes.video_id}?autoplay=${attributes.autoplay}&preload=${attributes.preload}&loop=${attributes.loop}&muted=${attributes.muted}`} loading="lazy" width="864" height="486" className="components-sandbox"
                  sandbox="allow-scripts allow-same-origin allow-presentation" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowFullScreen={true}></iframe>
        </div>
      </figure>
    );
  } else {
    return <div {...blockProps}></div>;
  }
}
