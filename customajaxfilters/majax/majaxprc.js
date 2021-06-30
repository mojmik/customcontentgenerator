var majaxModule=(function (my) {

    const mUrl = {
        prevUrl: "",
        params: [], 
        getCurUrl() {
            return window.location.href;
        },
        getCurBaseUrl() {
            return window.location.href.split('?')[0];
        },
        saveUrl() {            
            mUrl.prevUrl=mUrl.getCurUrl();
        },
        goBack() {            
            if (mUrl.getCurUrl() == mUrl.prevUrl || mUrl.prevUrl == "") history.go(-1);
            else window.location.href=mUrl.prevUrl;            
            return false;
        },
        readUrl() {
         //read all values from browser's address
         let url=mUrl.getCurUrl();
         
         mUrl.params=[];
         const urlParams = new URLSearchParams(window.location.search);
         urlParams.forEach(function(value, key) {
            mUrl.params[key]=value;
          });
        },
        writeUrl() {
         //put all values into browser's address
         let srcUrl=mUrl.getCurUrl();         
         let href=mUrl.getCurBaseUrl();
         let n=0;
         for (let key in this.params) {
             n++;
             if (n==1) href=href+"?"+key+"="+encodeURIComponent(this.params[key]);
             else href=href+"&"+key+"="+encodeURIComponent(this.params[key]);
         }
         if (href!=srcUrl) window.history.pushState({href: href}, '', href);	  
        },
        addParam(param,value) {
         this.params[param]=value;
         this.resetPagination();
        },
        resetPagination() {
            this.params["aktPage"]="";  
        },
        generateUrl(paramName,paramValue) {
         //return url with additional param
         let href=mUrl.getCurBaseUrl();
         let virtParams=this.params;
         virtParams[paramName]=paramValue;
         let n=0;
         for (let key in this.params) {            
            if (virtParams[key] !== null) {
                n++;
                if (n==1) href=href+"?"+key+"="+encodeURIComponent(virtParams[key]);
                else href=href+"&"+key+"="+encodeURIComponent(virtParams[key]);
            }            
          }
          return href;
        }

    };

    const majaxPrc = {
        ajaxSeq:0,
        thisFiringObjId:"",
        captcha:"",
        scrollPage:0,
        staticPages:0,        
        ajaxing:false,
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
                   my.majaxRender.drawResultsFunction(this.thisId,jsonObj);
                   pos=this.fullResp.indexOf("\n");
                }
            }
        },
        runAjax: function(firingElement,ajaxType="full") {
            //static inits
            let staticFields = jQuery('input[data-group="majax-init"]');
            staticFields.each(function (i,obj) {                
                let inputName=jQuery(this).attr('name');                 
                let val=obj.value;
                if (inputName=="staticPages") majaxPrc.staticPages=val;
                my.majaxRender.staticFields[inputName]=val;
            });

            if (ajaxType=="scroll") {
                if (majaxPrc.ajaxing) return false;
                let totalPages=my.majaxRender.staticFields["totalPages"];
                if (typeof totalPages === 'undefined') totalPages=my.majaxRender.totalPages;              
                if (parseInt(totalPages)>0 && ((parseInt(majaxPrc.scrollPage) + parseInt(majaxPrc.staticPages)) >= parseInt(totalPages))) {                     
                    return false;
                }
            } 
            majaxPrc.ajaxing=true;
            my.majaxRender.hideBack();             
            mUrl.readUrl(); //load parameters from url           
            var ajaxPar=majaxPrc.getAjaxParams(jQuery(firingElement),ajaxType);	 
            
            jQuery.ajax(majax.ajax_url, ajaxPar)
                       .done(function(dataOut)			{
                           //console.log('Complete response = ' + dataOut);
                           majaxPrc.ajaxing=false;
                       })
                       .fail(function(dataOut)			{
                           //console.log('Error: ', dataOut);
                       });
                       
        },
        getAjaxParams:function (varThisObj,ajaxType) { 
         var seqNumber = ++this.ajaxSeq;
         var thisId=0;	 
         var last_response_len = false;
         var objCategory="";
         var actionFunction='filter_rows';
         var aktPage=0;
         if (ajaxType=="full" || ajaxType=="scroll") {
            if (varThisObj.length!=0) {		
                objCategory=varThisObj.data('slug');
                let href=varThisObj.attr('href');
                if (href) {
                    if (objCategory=="pagination") {
                        //page number clicked
                        aktPage=varThisObj.data('page');                
                    }
                    else { 
                        actionFunction='single_row';	
                    }                
                }
                if (varThisObj[0].id=="majaxContactForm") {
                    actionFunction='contact_filled'; 
                    objCategory=mUrl.params["id"];
                }
                majaxPrc.thisFiringObjId=varThisObj[0].id;		
             }
    
             if (mUrl.params["id"] && actionFunction!='contact_filled') {
                actionFunction='single_row';	
                objCategory=mUrl.params["id"];
             }
             if (mUrl.params["aktPage"]) {     
                 //page number came in url                   
                aktPage=mUrl.params["aktPage"];            
             }
         }
         else if (ajaxType=="formInit") {
            actionFunction='formInit';  
            objCategory=varThisObj[0].id;
         }

         var outObj={
                        type: 'POST',
                        data: {
                              action: actionFunction,
                              category: objCategory,
                              mautaCPT: my.majaxRender.getType(),                              
                              language: my.majaxRender.language,
                              aktPage: aktPage,
                              security: majax.nonce
                        },                  
                        xhrFields: {
                            onprogress: function(e)	{
                                if (seqNumber === majaxPrc.ajaxSeq) { //check we are processing correct response
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
                                        majaxPrc.fullResponse.addResp(this_response);					
                                    }
                                }
                                else {
                                    //ignore this seq
                                }
                            }
                        }
                        
         };			

         if (ajaxType=="scroll") {
             outObj.beforeSend=null;
             majaxPrc.scrollPage+=1;
             outObj.data.aktPage=majaxPrc.scrollPage;             
         } else {
            my.majaxRender.sendClearFunction(actionFunction);
         }
         
         if (my.mStrings.isNonEmptyStr(majaxPrc.captcha)) {            
            outObj.data["captcha"]=majaxPrc.captcha;
         }
         

         if (majaxPrc.thisFiringObjId=="majaxContactForm") {
            let formData=my.majaxViewComponents.mForms.returnValues();
            for (let field in formData) {
                outObj.data[field]=formData[field];
            }
         }
         
         let filterByFields=true;
         if (actionFunction=="single_row") filterByFields=false;
         if (filterByFields) {
            var inputFields = jQuery('input[data-group="majax-fields"]');
            inputFields.each(function (i,obj) {
                let sliderId=jQuery(this).attr('data-mslider');	 
                let inputName=jQuery(this).attr('name'); 
                if (typeof sliderId !== 'undefined') {
                    //special slider input
                    let defaultVal = "0 - 1";                 
                    if (obj.value != defaultVal) {                                       
                        let fieldFormat=my.metaMisc.fieldformat[inputName];
                        fieldFormat = (fieldFormat == "") ? 2 : fieldFormat;
                        let aktVal=my.metaMisc.formatMetaVal(obj.value,0,fieldFormat,"fromFormat");
                        outObj.data[obj.name]=aktVal;
                        if (majaxPrc.thisFiringObjId == sliderId) {
                            //slider set by user, save to url
                            mUrl.addParam(inputName,aktVal);                        
                        } 
                    }
                    if (majaxPrc.thisFiringObjId != sliderId) {
                        //slider val has come in url
                        if (mUrl.params[inputName]) {
                            my.majaxSlider.setSlidersVals(sliderId,mUrl.params[inputName].split("|")[0],mUrl.params[inputName].split("|")[1]);
                            outObj.data[obj.name]=mUrl.params[inputName];
                        } 
                    }
                }  else {		
                        //ordinary input
                        if (obj.type == "checkbox") {
                            if (obj.checked==true) obj.value="1";
                            if (obj.checked==false) obj.value="0";
                        } 		
                        outObj.data[obj.name]=obj.value;
                        if (majaxPrc.thisFiringObjId == obj.id && obj.id) {
                            //input set by user or static, save to url
                            mUrl.addParam(inputName,obj.value);  
                        } else {
                            if (mUrl.params[inputName]) {
                                //input val has come in url                    
                                if (obj.type == "checkbox") {
                                    if (mUrl.params[inputName]==="1") obj.checked=true;
                                    else obj.checked=false;
                                }
                                else jQuery(obj).val(mUrl.params[inputName]);
                                outObj.data[obj.name]=mUrl.params[inputName];  
                            }
                        }
                }
            });
            
            //selects
            var selects=jQuery('select[data-group="majax-fields"]');
            selects.each(function (i,obj) {
                            //outObj.data[obj.name]=obj.value;				
                            //rozbaleni dat pro vyfiltrovani
                            var selectedText="";
                            var selectData=jQuery(obj).select2('data');	
                            var n=0;	                        
                            jQuery.each(selectData, function (selIndex,selObj) {
                                if (selObj.selected) { 
                                if (n>0) selectedText += "|";
                                selectedText += selObj.id;
                                n++;
                                }
                            });				
                            outObj.data[obj.name]=selectedText;
                            if (majaxPrc.thisFiringObjId == obj.id) {
                                //input set by user, save to url  
                            let selValues="";
                            jQuery.each(obj.options, function (selIndex,selObj) {
                                if (selObj.selected===true) { 
                                    if (selValues!="") selValues+="|"+selObj.value; 
                                    else selValues+=selObj.value; 
                                }
                            });
                            mUrl.addParam(obj.name,selValues);  
                            } else {                                                                                                                                
                                    if (mUrl.params[obj.name]) {
                                        //input val has come in url   
                                        outObj.data[obj.name]=mUrl.params[obj.name];
                                        jQuery.each(obj.options, function (selIndex,selObj) {
                                            if (mUrl.params[obj.name].indexOf(selObj.value ) !== -1) selObj.selected=true;                                      
                                        });
                                    }
                            }
                        
            });

         }
         
         mUrl.writeUrl();
         return outObj;
        }
    };

    my.majaxPrc=majaxPrc;
    my.mUrl=mUrl;

    return my;
}(majaxModule || {} ));