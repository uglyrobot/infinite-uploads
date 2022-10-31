const progress        = document.querySelector('.progress')
const progressBar     = progress.querySelector('.bar')
const uploadList      = document.querySelector('#upload-list')
const file_name = document.querySelector('#file_name')
const encod_progress = document.querySelector(".encod_progress")

var sha256 = function sha256(ascii) {
	function rightRotate(value, amount) {
		return (value>>>amount) | (value<<(32 - amount));
	};
	
	var mathPow = Math.pow;
	var maxWord = mathPow(2, 32);
	var lengthProperty = 'length'
	var i, j; // Used as a counter across the whole file
	var result = ''

	var words = [];
	var asciiBitLength = ascii[lengthProperty]*8;
	
	//* caching results is optional - remove/add slash from front of this line to toggle
	// Initial hash value: first 32 bits of the fractional parts of the square roots of the first 8 primes
	// (we actually calculate the first 64, but extra values are just ignored)
	var hash = sha256.h = sha256.h || [];
	// Round constants: first 32 bits of the fractional parts of the cube roots of the first 64 primes
	var k = sha256.k = sha256.k || [];
	var primeCounter = k[lengthProperty];
	/*/
	var hash = [], k = [];
	var primeCounter = 0;
	//*/

	var isComposite = {};
	for (var candidate = 2; primeCounter < 64; candidate++) {
		if (!isComposite[candidate]) {
			for (i = 0; i < 313; i += candidate) {
				isComposite[i] = candidate;
			}
			hash[primeCounter] = (mathPow(candidate, .5)*maxWord)|0;
			k[primeCounter++] = (mathPow(candidate, 1/3)*maxWord)|0;
		}
	}
	
	ascii += '\x80' // Append Ƈ' bit (plus zero padding)
	while (ascii[lengthProperty]%64 - 56) ascii += '\x00' // More zero padding
	for (i = 0; i < ascii[lengthProperty]; i++) {
		j = ascii.charCodeAt(i);
		if (j>>8) return; // ASCII check: only accept characters in range 0-255
		words[i>>2] |= j << ((3 - i)%4)*8;
	}
	words[words[lengthProperty]] = ((asciiBitLength/maxWord)|0);
	words[words[lengthProperty]] = (asciiBitLength)
	
	// process each chunk
	for (j = 0; j < words[lengthProperty];) {
		var w = words.slice(j, j += 16); // The message is expanded into 64 words as part of the iteration
		var oldHash = hash;
		// This is now the undefinedworking hash", often labelled as variables a...g
		// (we have to truncate as well, otherwise extra entries at the end accumulate
		hash = hash.slice(0, 8);
		
		for (i = 0; i < 64; i++) {
			var i2 = i + j;
			// Expand the message into 64 words
			// Used below if 
			var w15 = w[i - 15], w2 = w[i - 2];

			// Iterate
			var a = hash[0], e = hash[4];
			var temp1 = hash[7]
				+ (rightRotate(e, 6) ^ rightRotate(e, 11) ^ rightRotate(e, 25)) // S1
				+ ((e&hash[5])^((~e)&hash[6])) // ch
				+ k[i]
				// Expand the message schedule if needed
				+ (w[i] = (i < 16) ? w[i] : (
						w[i - 16]
						+ (rightRotate(w15, 7) ^ rightRotate(w15, 18) ^ (w15>>>3)) // s0
						+ w[i - 7]
						+ (rightRotate(w2, 17) ^ rightRotate(w2, 19) ^ (w2>>>10)) // s1
					)|0
				);
			// This is only used once, so *could* be moved below, but it only saves 4 bytes and makes things unreadble
			var temp2 = (rightRotate(a, 2) ^ rightRotate(a, 13) ^ rightRotate(a, 22)) // S0
				+ ((a&hash[1])^(a&hash[2])^(hash[1]&hash[2])); // maj
			
			hash = [(temp1 + temp2)|0].concat(hash); // We don't bother trimming off the extra ones, they're harmless as long as we're truncating when we do the slice()
			hash[4] = (hash[4] + temp1)|0;
		}
		
		for (i = 0; i < 8; i++) {
			hash[i] = (hash[i] + oldHash[i])|0;
		}
	}
	
	for (i = 0; i < 8; i++) {
		for (j = 3; j + 1; j--) {
			var b = (hash[i]>>(j*8))&255;
			result += ((b < 16) ? 0 : '') + b.toString(16);
		}
	}
	return result;
};

function tusUpload(element){
		const file = element.files[0];
		const name = JSON.stringify(file.name);
		const url = 'https://video.bunnycdn.com/library/56793/videos';
		const options = {
		  method: 'POST',
		  headers: {
		    accept: 'application/json',
		    'content-type': 'application/*+json',
		    AccessKey: '293abf14-8359-4f92-ba6e2d1a291b-c2cd-4f92',
		  },
		  body: '{"title":'+name+' }'	 
		};

		const library_id = "56793";
		const api_key = "293abf14-8359-4f92-ba6e2d1a291b-c2cd-4f92";
		const expiration_time = Math.floor(( Date.now() / 1000 ) + 3600 ).toString(); // 1 hour 
		fetch(url, options)
		  .then(res => res.json())
		  .then(json => {
			var upload = new tus.Upload(file, {
		    endpoint: "https://video.bunnycdn.com/tusupload",
		    retryDelays: [0, 3000, 5000, 10000, 20000, 60000, 60000],
		    headers: {
		        AuthorizationSignature: sha256(library_id + api_key + expiration_time + json.guid), // SHA256 signature (library_id + api_key + expiration_time + video_id)
		        AuthorizationExpire: expiration_time, // Expiration time as in the signature,
		        VideoId: json.guid, // The guid of a previously created video object through the Create Video API call
			    LibraryId: library_id,
			},
		    metadata: {
		        filetype: file.type,
		        title: name,
		    },

		    onError: function (error) { 
		    	if (error.originalRequest) {
			        if (window.confirm(`Failed because: ${error}\nDo you want to retry?`)) {
			          upload.start()
			          uploadIsRunning = true
			          return
			        }
			    } else {
			        window.alert(`Failed because: ${error}`)
			      }
		    },
		    onProgress: function (bytesUploaded, bytesTotal) { 
		    	const percentage = ((bytesUploaded / bytesTotal) * 100).toFixed(2)	
			    progressBar.style.width = `${percentage}%`
			    file_name.innerHTML = `${upload.file.name}`
			    //console.log("progressBar", bytesUploaded, bytesTotal, `${percentage}%`)
		    },
		    onSuccess: function () { 
		      setInterval(() => {
  					getbunnyVideo(json.guid);
				}, 10000);
		      file_name.innerHTML = `${upload.file.name}`
		      console.log("Download %s from %s", upload.file.name, upload.url)
		    }
		})

			console.log("Upload", upload);

		// Check if there are any previous uploads to continue.
		upload.findPreviousUploads().then(function (previousUploads) {
		    // Found previous uploads so we select the first one. 
		    if (previousUploads.length) {
		        upload.resumeFromPreviousUpload(previousUploads[0])
		    }

		    // Start the upload
		    upload.start()
		    uploadIsRunning = true
		})
	})
	.catch(err => console.error('error:' + err));
}



function getbunnyVideo(videoId){

	const options = {
      method: 'GET',
      headers: {
        Accept: 'application/json',
        AccessKey: '293abf14-8359-4f92-ba6e2d1a291b-c2cd-4f92'
      },
    };
    
    fetch(`https://video.bunnycdn.com/library/56793/videos/${videoId}`, options)
      .then((response) => response.json())
      .then((data) => {
        //console.log("Video:", data);

        //console.log(data.thumbnailFileName);

        console.log(`https://vz-a8691a32-d3c.b-cdn.net/${videoID}/${data.thumbnailFileName}`);

        if(data.status == 3){
        	encod_progress.innerHTML = "Uploading &nbsp" +data.encodeProgress+'%';
        }
        if(data.status == 4){
        	encod_progress.innerHTML = "Video" +data.encodeProgress+'% Processed...';
        }
      })
      .catch((error) => {
        console.error(error);
      });
}