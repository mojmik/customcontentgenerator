function saveAndAdd() {
	var $butt=jQuery('button.editor-post-publish-button__button');
	var customPost=jQuery('#post_type').val();
	var url='./post-new.php?post_type='+customPost;
	
	jQuery('button.editor-post-publish-button__button').click();  
	setTimeout(function() {				
				window.location=url;
			}, 2000);	
	return false;  
}


jQuery(function($){
	
	const mAutaAjax = {
		ajaxSeq:0,
		nonce:"mauta",
		statusSpan:null,
		fullResponse: {
            thisId:0,
            fullResp:"",  
            wholeResp:"",
            addResp: function(resp) {
               this.fullResp = this.fullResp + resp; 	
               this.wholeResp = this.wholeResp + resp; 	
               var pos=this.fullResp.indexOf("\n");
               while (pos!==-1) {
                   newObj="";	 
                   this.thisId++;
                   newObj=this.fullResp.slice(0,pos);
                   this.fullResp=this.fullResp.slice(pos+1);
                   let jsonObj=JSON.parse(newObj);
                   mAutaAjax.drawResultsMain(this.thisId,jsonObj);
                   pos=this.fullResp.indexOf("\n");
                }
            }
		},
		
		requestStack: {
			dataArr:[],
			successFuncArr:[],
			currentRequest:0,
			totalRequests:0,
			doingAjax:false,
			reset: function() {
				this.dataArr=[];
				this.successFuncArr=[];
				this.currentRequest=0;
				this.totalRequests=0;	
				this.doingAjax=false;
			},
			pushStack: function (data,successFunc) {
				this.dataArr.push(data);
				this.successFuncArr.push(successFunc);
				
			},
			toConsole: function(stackelement) {
				console.log(stackelement.data.doajax + " "+stackelement.data.from+" "+stackelement.data.to);
				//if (this.dataArr.length>0) mAutaAjax.requestStack.popStack();
			},
			popStack: function() {
				let popstackItem={};
				if (this.dataArr.length<1) return false;
				
				this.doingAjax=true;
				popstackItem.data=this.dataArr.shift();
				popstackItem.func=this.successFuncArr.shift();
				this.toConsole(popstackItem);
				mAutaAjax.runAjax(popstackItem.data,popstackItem.func);				
				this.currentRequest++;
				this.updateProgress();
			},
			go: function() {
				this.totalRequests=this.dataArr.length;
				this.currentRequest=0;				
				this.popStack();
			},
			updateProgress: function() {
				jQuery("#ajaxprogress").html("<span>"+this.currentRequest+"/"+this.totalRequests+"</span>");
			}
		},
		doDrawResults: function(data) {
			//dynamically assigned
		},
		drawResultsMain: (thisId,data) => {				
			//jQuery('#mautaCSVimportResults').append(jsonObj.result);
			mAutaAjax.doDrawResults(data);			
		}, 		
		runAjax: function(data,successFunc) {					
			var ajaxPar=this.prepareAjax(data);	 
			mAutaAjax.doDrawResults=successFunc;
			mAutaAjax.beforeSend();					
			jQuery.ajax(ajaxurl, ajaxPar)
					   .done(function(dataOut)	{
						   if (mAutaAjax.requestStack.popStack()===false) { 
								mAutaAjax.hideLoaderAnim();
						   }
					   })
					   .fail(function(dataOut)			{

					   });
					   
		},
		beforeSend: function() {
			jQuery('.majax-loader').css('display','flex');	
			jQuery('.majax-loader').removeClass('majax-loader-disappear-anim'); 
		},
		hideLoaderAnim: () => {
            jQuery('.majax-loader').addClass('majax-loader-disappear-anim');
        },
		prepareAjax: function(addData=null) {
			var seqNumber = ++this.ajaxSeq;
         	var thisId=0;	 
         	var last_response_len = false;
			var ajaxRequest={
				type: 'POST',
				data: {
					  security: mAutaAjax.nonce
				},
				xhrFields: {
					onprogress: function(e)	{
						if (seqNumber === mAutaAjax.ajaxSeq) { //check we are processing correct response
							var this_response, response = e.currentTarget.response;
							if(last_response_len === false)	{
								//first response in stream
								this_response = response;
								last_response_len = response.length;
							}
							else {
								//another response in stream
								this_response = response.substring(last_response_len);
								last_response_len = response.length;
							}
							thisId++;
							if (this_response!="") {
								//we have a response
								mAutaAjax.fullResponse.addResp(this_response);					
							}
						}
						else {
							//ignore this seq
						}
					}
				}
				
			};
			if (addData) {				
				for (let item in addData) {
					ajaxRequest.data[item]=addData[item];
				}				
			} 			
			return ajaxRequest;
		}		
	}

	var runProcess = function(doajax,table,csvtype,from=0,to=0,ajaxSuccess=null) {
		var data = {
			action: "importCSV",
			doajax: doajax,	
			table: table,
			csvtype: csvtype,
			from:from,
			to:to
		}
		if (ajaxSuccess==null) {
			ajaxSuccess = function( data ) {								
				jQuery("#mautaCSVimportResults").append(data.result + "<br />");				
			}		
		}		
		//mAutaAjax.runAjax(data,ajaxSuccess);
		mAutaAjax.requestStack.pushStack(data,ajaxSuccess);
	}

	jQuery("form#mautaAddCSV").submit(function() {	
		var doajax = $("input[name='doajax']").val();			
		var csvtype = $("input[name='csvtype']").val();
		var table = $("input[name='table']").val();
		var totalRecords = $("input[name='totalRecords']").val();
		for (n=0;n<Math.ceil(totalRecords/100);n++) {
			runProcess(doajax,table,csvtype,n*100,(n+1)*100);
		}
		runProcess("createCats",table,csvtype);
		
		runProcess("getCatsCnt",table,csvtype,0,50,function(data) {
			//console.log(data.result);
			for (n=0;n<Math.ceil(data.result/50);n++) {
				runProcess("udpateCatsDesc2",table,csvtype,n*50,(n+1)*50);
			}
			mAutaAjax.requestStack.go();
		});		
		//runProcess("udpateCatsDesc",table,csvtype);		

		mAutaAjax.requestStack.go();
	    return false;
	 });
	 
	 jQuery("#catdescajax").on('click', function(event) {
		updateCatDesc();
		return false;
	 });

	 jQuery("#posts2ded").on('click', function(event) {
		var csvtype = "cjcsv";	
		var table="";	
		var postsAtOnce=100;
		runProcess("getPostsCnt",table,csvtype,0,0,function(data) {
			console.log(data.result);
			for (n=0;n<Math.ceil(data.result/postsAtOnce);n++) {
				let lastFn=null;
				if (n>=Math.ceil(data.result/postsAtOnce)-1) {
					lastFn=function() {
						console.log("posts create dedicated table done");
					}
				}
				runProcess("createFromPosts",table,csvtype,n*postsAtOnce,(n+1)*postsAtOnce,lastFn);
			}
			mAutaAjax.requestStack.go();
		});
		mAutaAjax.requestStack.go();
		return false;
	 });

	 //todo: process all files in bulk
	 /*
	 jQuery("form#csvBulkImport").submit(function() {	
		$("input[data-fn='csvbulkfn']").each(function (i,obj) {
			var fn=obj.value;		
			var name=obj.name;			
			createPostsForInput(name,fn);
		});
	    return false;
	 });
	 */

	 var updateCatDesc = function() {
		var csvtype = "cjcsv";
		var table="wp_mauta_cj_cj_import";

		runProcess("getCatsCnt",table,csvtype,0,50,function(data) {
			console.log(data.result);					
			for (n=0;n<Math.ceil(data.result/20);n++) {
				let lastFn=null;
				if (n>=Math.ceil(data.result/20)-1) {
					lastFn=function() {
						console.log("cat desc done");
					}
				}
				runProcess("udpateCatsDesc2",table,csvtype,n*20,(n+1)*20,lastFn);
			}
			mAutaAjax.requestStack.go();
		});
		mAutaAjax.requestStack.go();
	 }

	 
	 var loadPostsFromCSV = function(data) {
		var doajax = "makeposts";
		var csvtype = "cjcsv";
		var table="wp_mauta_cj_cj_import";
		mAutaAjax.statusSpan.text("csv imported");
		console.log("total res:"+data.result);
		var totalRecords = data.result;
		let doneFn=null;
		for (let n=0;n<Math.ceil(totalRecords/100);n++) {				
			if (n>=Math.ceil(totalRecords/100)-1) {
				//last posts chunk
				doneFn=function() {
					mAutaAjax.statusSpan.text("creating cats");  
					
					runProcess("createCats",table,csvtype,0,0,function(data) {
						mAutaAjax.statusSpan.text("cats created");  							
					});										
					mAutaAjax.requestStack.go();										
				}	
			} else {
				doneFn=function(data) {
					mAutaAjax.statusSpan.text("creating posts");  
					
				}
			}
			runProcess(doajax,table,csvtype,n*100,(n+1)*100,doneFn);			
		}
		mAutaAjax.requestStack.go();			
	 }

	 var createPostsForInputChunky = function(name,fn) {
		mAutaAjax.requestStack.reset();
		//go through all files big csv
		
		var doajax = "makeposts";
		var csvtype = "cjcsv";
		var table="wp_mauta_cj_cj_import";
		
		mAutaAjax.statusSpan=$("span[data-fn='statuscsvbulk-"+name+"']");
		mAutaAjax.statusSpan.text("processing");
		runProcess("countcsvlines",table,csvtype,fn,0,function(data) {
			var totalLines = data.result;
			console.log(totalLines + " lines in "+fn);
			var doneCSVfn=null;
			for (l=0;l<Math.ceil(totalLines/500);l++) {				
				if (l>=Math.ceil(totalLines/500)-1) {
					doneCSVfn=loadPostsFromCSV;			
				} else {
					doneCSVfn=function() {
						mAutaAjax.statusSpan.text("importing csv");  							
					}
				}
				runProcess("ajaximportcsv",table,csvtype,fn,l*500,doneCSVfn);
			}
			mAutaAjax.requestStack.go();
		});
		mAutaAjax.requestStack.go();
	 }
	 

	 var createPostsForInput = function(name,fn) {
		mAutaAjax.requestStack.reset();
		//go through all files small csv load at once
		
		var doajax = "makeposts";
		var csvtype = "cjcsv";
		var table="wp_mauta_cj_cj_import";
			
		mAutaAjax.statusSpan=$("span[data-fn='statuscsvbulk-"+name+"']");
		mAutaAjax.statusSpan.text("processing");
		runProcess("ajaximportcsv",table,csvtype,fn,0,function(data) {
			mAutaAjax.statusSpan.text("csv imported");
			//make posts
			loadPostsFromCSV(data);
		});			
		mAutaAjax.requestStack.go();
	 }

	jQuery("form[data-form='csvBulkImport']").submit(function(obj) {	
		let inputFn = $("input[data-fn='csvbulkfn']",this)[0];
		createPostsForInputChunky(inputFn.name,inputFn.value);
		return false;
 	});
	 
			

	function addRemoveListener() {
		jQuery("form.removeCPT").submit(function() {	
			var thisForm=$(this);
			var formData={};
			thisForm.find( '[name]' ).each( function( i , v ){
				let input = $( this ); // resolves to current input element.
				let name = input.attr( 'name' );
				let value = input.val();
				formData[name] = value;
			});
	
			jQuery.ajax({
				   type: "POST",
				   url: ajaxurl,
				   data: {
					action: "editCPT",
					slug: formData["slug"],
					cafActionRemove: formData["cafActionRemove"]
				  },
				success: function( data ) {
					let jsonObj=JSON.parse(data);
					jQuery("#"+jsonObj.id).remove();
				 }
		   });
		   return false;
		 });
	}

	jQuery("form.createCPT").submit(function() {	
		var singular = $("input[name='singular']").val();		
		var plural = $("input[name='plural']").val();		
		var cafAction = $("input[name='cafAction']").val();	
		jQuery.ajax({
			   type: "POST",
			   url: ajaxurl,
			   data: {
				action: "createCPT",
				singular: singular,
				cafAction: cafAction,
				plural: plural,
			  },
			success: function( data ) {
				//window.location.reload();
				jQuery("#mAutaCustomPosts").append(data);
				addRemoveListener();
			 }
	   });
	   return false;
	 });

	
	/*
	 jQuery("form.editCPT").submit(function() {	
		var thisForm=$(this);
		var formData={};
		thisForm.find( '[name]' ).each( function( i , v ){
			let input = $( this ); // resolves to current input element.
			let name = input.attr( 'name' );
			let value = input.val();
			formData[name] = value;
		});

		jQuery.ajax({
			   type: "POST",
			   url: ajaxurl,
			   data: {
				action: "editCPT",
				singular: formData["singular"],
				cafAction: formData["cafAction"],
				slug: formData["slug"],
				plural: formData["plural"],
				specialType: formData["specialType"],
				tableType: formData["tableType"],
				cafActionEdit: formData["cafActionEdit"],
			  },
			success: function( data ) {
				window.location.reload();
			 }
	   });
	   return false;
	 });
	 */

	 addRemoveListener();

// on upload button click
$('body').on( 'click', '.icon-upl', function(e){
 
	e.preventDefault();

	var button = $(this),
	custom_uploader = wp.media({
		title: 'Insert image',
		library : {
			// uploadedTo : wp.media.view.settings.post.id, // attach to the current post?
			type : 'image'
		},
		button: {
			text: 'Use this image' // button label text
		},
		multiple: false
	}).on('select', function() { // it also has "open" and "close" events
		var attachment = custom_uploader.state().get('selection').first().toJSON();
		//button.html('<img style="width:150px" src="' + attachment.url + '">').next().val(attachment.id).next().show();			
		button.parent().children('.icon-rmv').show();
		button.parent().children('input[name="icon"]').val(attachment.id);	
		button.html('<img style="width:150px" src="' + attachment.url + '">');		
		button.blur();
	}).open();

	});

	// on remove button click
	$('body').on('click', '.icon-rmv', function(e){

		e.preventDefault();

		var button = $(this);		
		button.parent().children('input[name="icon"]').val('');		
		button.parent().children('.icon-upl').html('Upload image');	
		button.hide();	
	});
});