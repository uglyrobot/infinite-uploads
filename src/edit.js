/**
 * WordPress components that create the necessary UI elements for the block
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-components/
 *
 * See https://github.com/WordPress/gutenberg/blob/trunk/packages/block-library/src/video/
 */
/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-block-editor/#useBlockProps
 */
import { PanelBody, Placeholder, Spinner } from '@wordpress/components';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { __, sprintf } from '@wordpress/i18n';
import { useRef, useEffect, useState } from '@wordpress/element';


import Uppy from '@uppy/core'
import Tus from '@uppy/tus'
import { DragDrop, StatusBar, useUppy } from '@uppy/react'
import UppyCreateVid from './edit-uppy-plugin'
import VideoCommonSettings from './edit-common-settings';
import { sha256 } from 'js-sha256';

//pulled from wp_localize_script later


/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @param {Object}   props               Properties passed to the function.
 * @param {Object}   props.attributes    Available block attributes.
 * @param {Function} props.setAttributes Function that updates individual attributes.
 *
 * @return {WPElement} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
  const LIBRARY_ID = BUNNYTEST.libraryId;
  const API_KEY    = BUNNYTEST.apiKey;

	const blockProps = useBlockProps();

  /* Possible video statuses
  Created = 0
  Uploaded = 1
  Processing = 2
  Transcoding = 3
  Finished = 4
  Error = 5
  UploadFailed = 6
  */
  const [videoStatus, setVideoStatus] = useState(0);

  const [encodeProgress, setEncodeProgress] = useState(0);
  const [isUploading, setUploading] = useState(false);
  const [intervalId, setIntervalId] = useState(0);

  useEffect( () => {
    if ( attributes.video_id ) {
      getVideo();
    }

    //clear interval on destroy
    return () => stopPollVideo();
  }, [] );

  const startPollVideo = () => {
    console.log('Maybe skip start interval, existing:',intervalId)
    if (intervalId) {
      console.log('Skip start interval, existing:',intervalId)
      return;
    }

    const newIntervalId = setTimeout(() => {
      getVideo();
    }, 10000);
    console.log('creating new interval:',newIntervalId)
    setIntervalId(newIntervalId);
  }

  const stopPollVideo = () => {
    console.log('Clearing interval:',intervalId)
    if(intervalId) {

      clearTimeout(intervalId);
      setIntervalId(0);
    }
  }

  const uppy = useUppy(() => {
    return new Uppy({
      debug: true,
      restrictions: {
        maxNumberOfFiles: 1,
        allowedFileTypes: ['video/*'],
      },
      autoProceed: true,
      allowMultipleUploadBatches: false,
      onBeforeUpload: (files) => {
        //TODO trigger error if video_id is null
      }
    })
      .use(Tus, {
        endpoint: 'https://video.bunnycdn.com/tusupload',
        retryDelays: [0, 1000, 3000, 5000],
        onBeforeRequest: (req) => {
          //TO FIX: attributes.video_id is undefined
          console.log('VideoId props:', blockProps.video_id );
          if ( blockProps.video_id ) {
            setAttributes({video_id: blockProps.video_id});
            attributes.video_id = blockProps.video_id;//I don't know why this is needed
          }
          console.log('VideoId attr:', attributes.video_id );

          let expiration = Math.floor(( Date.now() / 1000 ) + 3600 ).toString(); // 1 hour from now
          req.setHeader('AuthorizationSignature', sha256(LIBRARY_ID + API_KEY + expiration + blockProps.video_id));
          req.setHeader('AuthorizationExpire', expiration );
          req.setHeader('VideoId', blockProps.video_id );
          req.setHeader('LibraryId', LIBRARY_ID );
        },
      })
      .use(UppyCreateVid, {libraryId: LIBRARY_ID, apiKey: API_KEY, blockProps}) //our custom plugin

  });

  uppy.on('upload', (data) => {
    // data object consists of `id` with upload ID and `fileIDs` array
    // with file IDs in current upload
    // data: { id, fileIDs }
    setUploading(true);
  })

  uppy.on('complete', result => {
    console.log('successful files:', result.successful)
    console.log('failed files:', result.failed)
    setUploading(false);
    getVideo();
  })

  function getVideo() {
    const options = {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        AccessKey: API_KEY
      },
    };

    fetch(`https://video.bunnycdn.com/library/${LIBRARY_ID}/videos/${attributes.video_id}`, options)
      .then((response) => response.json())
      .then((data) => {
        console.log("Video:", data);
        setVideoStatus(data.status);
        setEncodeProgress(data.encodeProgress);

        if (data.status === 4 ) {
          stopPollVideo();
        } else {
          startPollVideo();
        }
      })
      .catch((error) => {
        console.error(error);
      });
  }

  if ( ! isUploading && attributes.video_id ) {
    if ( videoStatus === 4 ) {
      return (
        <div {...blockProps}>
          <figure class="wp-embed-aspect-16-9 wp-has-aspect-ratio wp-block-embed is-type-video">
            <div class="wp-block-embed__wrapper">
              <iframe src={`https://iframe.mediadelivery.net/embed/56793/${attributes.video_id}?autoplay=${attributes.autoplay}&preload=${attributes.preload}&loop=${attributes.loop}&muted=${attributes.muted}`} loading="lazy" width="864" height="486" className="components-sandbox" sandbox="allow-scripts allow-same-origin allow-presentation" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowFullScreen="true"></iframe>
            </div>
          </figure>
        </div>
      );
    } else {
      let label = '';
      if ( videoStatus === 3 ) {
        label = sprintf( __( 'Video %d%% encoded...', 'infinite-uploads' ), encodeProgress );
      } else {
        label = sprintf( __( 'Video %d%% processed...', 'infinite-uploads' ), encodeProgress );
      }
      return (
        <div {...blockProps}>
          <Placeholder>
            { videoStatus === 3 ? <img alt="Video thumbnail" src={`https://vz-a8691a32-d3c.b-cdn.net/${attributes.video_id}/thumbnail.jpg`} /> : '' }
            <Spinner progress={encodeProgress} /> {label}
          </Placeholder>
        </div>
      );
    }
  } else {
    return (
    <div {...blockProps}>
      <div className="uppy-wrapper">
      { ! isUploading ?
        <DragDrop
          width="100%"
          height="100%"
          // assuming `props.uppy` contains an Uppy instance:
          uppy={uppy}
          locale={{
            strings: {
              // Text to show on the droppable area.
              // `%{browse}` is replaced with a link that opens the system file selection dialog.
              dropHereOr: __( 'Drop video file here or %{browse}.', 'infinite-uploads' ),
              // Used as the label for the link that opens the system file selection dialog.
              browse: __( 'browse', 'infinite-uploads'),
            },
          }}
        />
        : ''}
      <StatusBar
        // assuming `props.uppy` contains an Uppy instance:
        uppy={uppy}
        hideUploadButton={false}
        hideAfterFinish={true}
        showProgressDetails
      />
      </div>
      <InspectorControls>
        <PanelBody title={ __( 'Settings' ) }>
          <VideoCommonSettings
            setAttributes={ setAttributes }
            attributes={ attributes }
          />
        </PanelBody>
      </InspectorControls>
    </div>
  );
  }
}