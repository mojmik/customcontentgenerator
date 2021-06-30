var majaxModule=(function (my) {
    const htmlTemplate = {
        srcHtml: [],
        addTemplate: function(htmlObj) {
            for (let key in htmlObj) {
                this.srcHtml[key]=htmlObj[key];
            }            
        },
        getTemplate: function(name) {
           return this.srcHtml[name];
        },
        renderOut: function(name,vals) {
            let out=this.srcHtml[name];
            out=this.renderHtml(out,vals);
            return out;
        },
        renderHtml: function(out,vals) {
            const paramsPattern = /{(.*?)}/g;
            let extractParams = out.match(paramsPattern);
            for (let m in extractParams) {
                let valName=extractParams[m].substring(1,extractParams[m].length-1);
                let val=undefined;
                if (valName.startsWith("metaOut")) {
                  let metaName=valName.substring(8,valName.length-1);
                  let metaName2="mauta_" + majaxRender.customType + "_" + metaName;
                  if (typeof vals["metaOut"][metaName2] !== 'undefined') val=vals["metaOut"][metaName2];
                  else val=vals["metaOut"][metaName];
                } else {
                    val=vals[valName];                    
                    if (typeof val === 'undefined') val=vals["jsonObj"][valName];           
                    if (typeof val === 'undefined') val=majaxRender[valName];                     
                    if (typeof val === 'undefined') val=""; 
                }                
                out=out.replace("{"+valName+"}",val);
            }
            return out;
        }
    }    
    const majaxRender = {   
        customType : "",
        language: "",
        mainClass: "",
        totalPages:0,
        staticFields:{},
        getType: function() {
            if (majaxRender.customType=="") {                
                majaxRender.customType=jQuery('input[name="type"]').val();
            } 
            return majaxRender.customType;
        },     
        postTemplate: (id,jsonObj) => {         
            let meta=jsonObj.meta;
            let image=jsonObj.image;
            let templateName=jsonObj.templateName;
            let featuredText=[];
            //
            let metaOut=[];
            for (let n=0;n<5;n++) {
                metaOut[n]="";    
            }            
        
            for (const property in meta) {
                let metaIcon=my.metaMisc.icons[property];
                let displayOrder=my.metaMisc.displayorder[property];
                let metaTitle=my.metaMisc.title[property];
                let htmlTemplate=my.metaMisc.htmlTemplate[property]; 
                let virtVal=my.metaMisc.virtVal[property]; 
                let formattedVal=my.metaMisc.formatMetaVal(meta[property],0,my.metaMisc.fieldformat[property],"toFormat",true);
                let htmlTemplatePresent=false;
                if (my.mStrings.isNonEmptyStr(htmlTemplate)) {
                    htmlTemplate=htmlTemplate.replace("${formattedVal}",formattedVal);
                    htmlTemplate=htmlTemplate.replace("${metaIcon}",metaIcon);
                    metaVal=htmlTemplate;  
                    htmlTemplatePresent=true;
                }
                else metaVal=formattedVal;

                if (typeof metaIcon!== 'undefined' && metaIcon!="") metaIcon=`<img src='${metaIcon}' />`;
                else metaIcon=`<span>${metaTitle}</span> `;	
                if (displayOrder<20) {
                    //meta group 0
                    if (!htmlTemplatePresent) metaOut[0]=metaOut[0] + `<div class='col meta'>${metaIcon}${metaVal}</div>`;
                    else metaOut[0]=metaOut[0] + metaVal;
                }
                if (displayOrder>=20 && displayOrder<=30) {  
                    if (!htmlTemplatePresent) metaOut[1]=metaOut[1] + `<div class='col meta'>${metaIcon}${metaVal}</div>`;
                    else metaOut[1]=metaOut[1] + metaVal;
                }
                if (displayOrder>30 && displayOrder<=40) {
                    //meta group 2                  
                    if (metaVal == null) metaVal="neuvedeno";
                    metaOut[3]=metaOut[3] + `
                    <div class='col-sm-3'>
                        <span>${my.metaMisc.title[property]}</span>
                        <div class='row'>
                            <span>
                             ${metaVal}
                            </span>
                        </div> 
                    </div>`
                }
                if (displayOrder>40 && displayOrder<=50) {
                 //featured group
                 featuredText.push(metaVal);
                }
                if (displayOrder>50 && displayOrder<=60) {
                    metaOut[property]=metaVal;
                }
            }	
            
            let featuredHtml="";
            featuredText.forEach(function (val,n) {
                featuredHtml+=`<div class='stripes stripe${n+1}'>${val}</div>`;
            });

            if (image!="") {
                image=`<img src='${image}' />`;
            }    
             
            let srcVals=[];
            srcVals["id"]=id;
            srcVals["jsonObj"]=jsonObj;
            srcVals["image"]=image;
            srcVals["metaOut"]=metaOut;
            srcVals["featuredHtml"]=featuredHtml;
            let outSrc=htmlTemplate.renderOut(templateName,srcVals);
            return outSrc;            
        },
        postTemplateEmpty: (id,content) => { 
            let metaOut="";	
            return(
            `
            <div class='majaxout' id='majaxout${id}'>
            ${content}
            </div>
            `);
        },
        postTemplateCounts: (meta) => {		
            if (meta["meta_key"]=="clearall" && meta["meta_value"]=="clearall") {
                //first record- clearall
                my.mCounts.clearAll();		
                return;
            }				
            //update inputs counts
            if (meta["meta_key"]=="endall" && meta["meta_value"]=="endall") { 
                //last  record- processing
                for (let key in my.mCounts.metaCounts) {
                    let mMeta=my.mCounts.metaCounts[key];
                    let filterElement=jQuery('#custField'+mMeta.metaKey);
                    let labelFor=jQuery('label[for="custField'+mMeta.metaKey);	
                    let cnt="("+mMeta.rowsCount+")";	
                    if (typeof filterElement[0] !== 'undefined' && filterElement[0].type=="checkbox" && mMeta.metaName == "1") {
                        let followingElement=labelFor.next();
                        let spanCounter="<span class='counter'>"+cnt+"</span>";
                        if (followingElement.length>0 && followingElement[0].tagName=="SPAN") {
                            followingElement.text(cnt);
                        } else {
                            labelFor.after(spanCounter);
                            //console.log(spanCounter);
                        }
                        
                    }
                }	
                //update selects
                jQuery(".majax-select").trigger('change.select2');	
                
                
                return;
            }
            my.mCounts.addMetaCount(my.mStrings.mNormalize(meta["meta_key"]),my.mStrings.mNormalize(meta["meta_value"]),meta["count"]);
        },
        postTemplatePagination: (pages) => {
            let content="";
            let n=0;
            let aktPage=0;
            let cntPage=0;
            for (let page in pages) { 
                if (pages[page]=="2") {
                 aktPage=n;
                }
                if (pages[page]!="pagination") n++;
            }   
            
            let totalPages=my.majaxRender.staticFields["totalPages"];
            if (typeof totalPages === 'undefined') totalPages=my.majaxRender.totalPages;  
            if (parseInt(totalPages)>n) { 
                n=parseInt(totalPages);
                aktPage=parseInt(my.mUrl.params["aktPage"]);
            }
            cntPage=n;
            if (cntPage==1) return "";
            let p=0;
            let url="";
            for (p=0;p<cntPage;p++) {
                if (p==0 || p==cntPage-1 || (p>aktPage-3 && p<aktPage+3) || p==aktPage) {
                    if (p!=aktPage) {
                        if (p!=0) url=my.mUrl.generateUrl("aktPage",p);
                        else url=my.mUrl.generateUrl("aktPage",null);
                        content+=`
                        <span><a data-slug='pagination' data-page='${p}' href='${url}'>${p+1}</a></span>
                        `;
                    } else {
                        content+=`
                        <span>${p+1}</span> 
                        `; 
                    }
                } 
                else {
                    if ((p==aktPage-3) || (p==aktPage+3)) content+=`..`;
                }
            }
            return(
                `    
                <div class='mpagination'>            
                ${content}                
                </div>
                `);   
        },
        sendClearFunction: (firingAction) => {
            if (firingAction!="formInit") { //dont wipe window in special cases (static forms etc.)
                jQuery('#majaxmain').empty();				 
            }
            
            jQuery('#majaxmain').append(my.majaxViewComponents.majaxLoader);
            if (firingAction!="single_row") jQuery('.majax-loader').css('display','flex');	 
        },
        hideLoaderAnim: () => {
            jQuery('.majax-loader').addClass('majax-loader-disappear-anim');
        },
        animateMajaxBox: (thisHtml,thisId) => {
            jQuery('#majaxmain').append(thisHtml);
            //jQuery("#majaxout"+thisId).fadeIn("slow");														
            jQuery("#majaxout"+thisId).css("display", "flex").hide().fadeIn("slow");
            //jQuery('.majax-loader').addClass('majax-loader-disappear-anim');
            majaxRender.hideLoaderAnim();
        },
        showBack: () => {     
             jQuery("#majaxform").hide();
             jQuery("#majaxback").show();
        },
        hideBack: () => { 
            jQuery("#majaxform").show();
            jQuery("#majaxback").hide();
        },
        drawResultsFunction: (thisId,jsonObj) => {	
            if (jsonObj.title=="majaxcounts") thisHtml=majaxRender.postTemplateCounts(jsonObj);
            else if (jsonObj.title=="buildInit") {
                my.metaMisc.addMetaMisc(jsonObj.misc);
                htmlTemplate.addTemplate(jsonObj.htmltemplate);
                majaxRender.language=jsonObj.language;
                majaxRender.mainClass=jsonObj.mainClass;
                majaxRender.totalPages=jsonObj.totalPages;
                //update sliders min-max
                my.majaxSlider.initSlidersMinMax(); 
            }
            else if (jsonObj.title=="pagination") {                
                if (my.majaxPrc.scrollPage>0) {
                    jQuery('.mpagination').remove();    
                } else {
                    let thisHtml=majaxRender.postTemplatePagination(jsonObj);
                    jQuery('#majaxmain').append(thisHtml);                    
                }
            }
            else if (jsonObj.title=="action") {                                
                majaxRender.hideLoaderAnim();   
                if (jsonObj.htmlSrc) {
                    let htmlOut=htmlTemplate.renderHtml(jsonObj.htmlSrc,jsonObj);
                    jQuery('#majaxmain').append(htmlOut);
                }             
                if (jsonObj.flag=="form-show") {
                    my.majaxViewComponents.majaxContactForm.initDefault("majaxContactForm",jsonObj.fields,jsonObj.siteKey);
                }         
                if (jsonObj.flag!="form-ok") majaxRender.showBack();                
            }            
            else if (jsonObj.title=="empty") {
                thisHtml=majaxRender.postTemplateEmpty(thisId,jsonObj.content);
                majaxRender.animateMajaxBox(thisHtml,thisId);			
            }
            else { 
                thisHtml=majaxRender.postTemplate(thisId,jsonObj);
                majaxRender.animateMajaxBox(thisHtml,thisId);			
            }
         }
    }

    my.majaxRender=majaxRender;
    my.htmlTemplate=htmlTemplate;
    return my;

}(majaxModule || {} ));
