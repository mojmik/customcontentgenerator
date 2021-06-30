var majaxModule=(function (my) {
    const majaxViewComponents = {
        majaxContactForm: { 
            formElement: null,
            captchaWidgetId:null,
            initCaptchaWidget: (siteKey) => {
                if (window.grecaptcha === undefined || window.grecaptcha.render===undefined) {
                    setTimeout(function(){ majaxViewComponents.majaxContactForm.initCaptchaWidget(); }, 500)
                    return;
                };
           
                    if (jQuery('#myCaptcha').length>0) {
                        majaxViewComponents.majaxContactForm.captchaWidgetId = grecaptcha.render( 'myCaptcha', {
                            'sitekey' : siteKey,  // required
                            'theme' : 'light',  // optional
                            /*'callback': 'verifyCallback'*/  // optional
                          });
                    }
           
            },
            initMain: (formName,fields,siteKey="") => {
                majaxViewComponents.majaxContactForm.formElement=jQuery("#"+formName);
                majaxViewComponents.mForms.setForm(majaxViewComponents.majaxContactForm.formElement);
                this.postedFields=[];
                majaxViewComponents.mForms.addInputs(fields);
                if (siteKey!="") my.majaxViewComponents.majaxContactForm.initCaptchaWidget(siteKey); 
                
                jQuery(majaxViewComponents.majaxContactForm.formElement).on('submit', function(event) {				
                    event.preventDefault();	      
                    if (my.majaxViewComponents.validateForm(this)) {
                        my.mUrl.addParam("formsent","1");
                        my.mUrl.writeUrl();                        
                        my.majaxPrc.captcha=grecaptcha.getResponse(majaxViewComponents.majaxContactForm.captchaWidgetId);
                        my.majaxPrc.runAjax(this);                                            		
                    }                                  
			        return false;
                });     
                jQuery("#majaxContactForm input[type='text']").on('focus', function (event) {
                      let prev=jQuery(this).prev();
                      if (typeof prev !=='undefined' && prev.data('formerr')=="1") jQuery(prev).text("");                        
                });
            },
            initDefault: (formName,fields,siteKey) => {
                majaxViewComponents.majaxContactForm.initMain(formName,fields,siteKey);
                jQuery(document).on("click", "label.leisLabel", function(e) {               		                            
                    e.stopImmediatePropagation();
                    let forCheckBox=jQuery(this).attr("for");
                    let inputCheckBox=jQuery("#"+forCheckBox+"ChBox");
                    let inputCheckBoxVal=jQuery("#"+forCheckBox+"ChBox").prop('checked');
                    jQuery(inputCheckBox).prop('checked', !inputCheckBoxVal);                    
                    jQuery("#"+forCheckBox+"Box").toggleClass('checked');
                    return false;
                });
                

                jQuery("#pickDate").datepicker({
                        duration: '',
                        changeMonth: false,
                        minDate : 0,
                        changeYear: false,
                        yearRange: '2010:2050',
                        showTime: false,
                        time24h: true                      
                });
                jQuery("#dropDate").datepicker();

                jQuery(function($) {
                    $.datepicker.regional['cs'] = {
                        closeText: 'Zavřít',
                        prevText: '&#x3c;Dříve',
                        nextText: 'Později&#x3e;',
                        currentText: 'Nyní',
                        monthNames: ['leden', 'únor', 'březen', 'duben', 'květen', 'červen', 'červenec', 'srpen',
                          'září', 'říjen', 'listopad', 'prosinec'
                        ],
                        monthNamesShort: ['led', 'úno', 'bře', 'dub', 'kvě', 'čer', 'čvc', 'srp', 'zář', 'říj', 'lis', 'pro'],
                        dayNames: ['neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota'],
                        dayNamesShort: ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'],
                        dayNamesMin: ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'],
                        weekHeader: 'Týd',
                        dateFormat: 'dd/mm/yy',
                        firstDay: 1,
                        isRTL: false,
                        showMonthAfterYear: false,
                        yearSuffix: ''
                      };
                    
                      $.datepicker.setDefaults($.datepicker.regional['cs']);
                });
                
                
            },
            renderDefault: (name,jsonObj) => {
                if ((typeof jsonObj.content !== 'undefined') && jsonObj.content!="") {
                    return `
                    <div class='mpagination'>      
                        <div class="row2 frameGray">
                            <div class="yellowBand" id="enquiryP">
                                ${jsonObj.content}
                            </div>
                        </div>
                    </div>`;    
                }                
                return `${jsonObj.htmlSrc}`;
            },                
        },
        majaxLoader: () => `
        <div class="majax-loader" data-component="loader" style="display: none;">
        <svg width="38" height="38" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg">
        <defs>
        <linearGradient x1="8.042%" y1="0%" x2="65.682%" y2="23.865%" id="gradient">
        <stop stop-color="#ffc107" stop-opacity="0" offset="0%"></stop>
        <stop stop-color="#ffc107" stop-opacity=".631" offset="63.146%"></stop>
        <stop stop-color="#ffc107" offset="100%"></stop>
        </linearGradient>
        </defs>
        <g fill="none" fill-rule="evenodd">
        <g transform="translate(1 1)">
        <path d="M36 18c0-9.94-8.06-18-18-18" stroke="url(#gradient)" stroke-width="3"></path>
        <circle fill="#fff" cx="36" cy="18" r="1"></circle>
        </g>
        </g>
        </svg></div>	
        `,        
        mForms: {
            formElement:null,
            postedFields:[],
            formatFields: {
                "letters":/^[a-zA-Z]*$/,
                "latinletters": /^[\u0000-\u024F]+$/,
                "email": /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/,
                "phone": /^[0-9\(\)\+\s\-]*$/,
                "number":/^-?\d*\.?\d*$/,
                "date":/^\d{2}[./-]\d{2}[./-]\d{4}$/
            },
            formatErrors: {
                "letters":"Zadaný text musí obsahovat pouze písmena",
                "email": "Zadaný text musí být platný email",
                "phone": "Zadaný text musí být platné telefonní číslo",
                "number":"Zadaný text musí být platné číslo",
                "date":"Zadaný text musí být platné datum",
                "required":"Toto je povinné pole",
                "sameLikeName":"Kontrolní pole se neshoduje"
            },
            addInputs(fieldSet) {
                fieldSet.forEach(function(fields, key) {
                    //mUrl.params[key]=decodeURIComponent(value);
                    majaxViewComponents.mForms.addInput(fields);      
                });
                
            },
            addInput: function (fields) {
                idName=my.mStrings.stringOrDefault(fields["idName"]);
                inputType=my.mStrings.stringOrDefault(fields["inputType"]);
                mRequired=my.mStrings.stringOrDefault(fields["mRequired"],true);
                sameLikeName=my.mStrings.stringOrDefault(fields["sameLikeName"],false);
            
                this.postedFields.push({"name": idName,"type": inputType,"required": mRequired, "sameLikeName" : sameLikeName});                                  
            },
            check: function(postedFieldKey,val) {
                let postedField=this.postedFields[postedFieldKey];
                if (postedField["type"]=="checkbox" || postedField["type"]=="select" || postedField["type"]=="hidden") return true;                
                if (postedField["required"]===true && val == "") return this.formatErrors["required"];
                if (postedField["required"]===false && val == "") return true;
                if (postedField["type"]!=="textarea" && !this.formatFields[postedField["type"]].test(val)) return this.formatErrors[postedField["type"]];                
                if (postedField["sameLikeName"]!==false) {
                    let otherVal=this.getPostedFieldValByName(postedField["sameLikeName"]);
                    if (otherVal !== val) return this.formatErrors["sameLikeName"];
                }
                return true;
            },
            getPostedFieldValByName: function(name) {
                for (let field in this.postedFields) {
                  let otherFieldName=this.postedFields[field]["name"];
                  if (otherFieldName == name) {
                      return jQuery(this.formElement).find(`input[name="${otherFieldName}"]`).val();
                  }
                }
            },
            setForm(mForm) {
                this.formElement=mForm;
                this.postedFields=[];
            },
            returnValues: function() {
                let values=[];
                for (let field in this.postedFields) {
                    let theField=this.postedFields[field]["name"];
                    let theFieldType=this.postedFields[field]["type"];
                    
                    if (theFieldType=="checkbox") {                        
                        values[theField]=jQuery(this.formElement).find(`input[name="${theField}"]`).prop("checked") ? "1" : "0";                        
                    } else if (theFieldType=="textarea") {
                        values[theField]=jQuery(this.formElement).find(`textarea[name="${theField}"]`).val();                        
                    }                    
                    else if (theFieldType=="select") {
                        let selectData=jQuery(this.formElement).find(`select[name="${theField}"]`).select2('data');	
                        let n=0;	     
                        var selectedText="";
                        jQuery.each(selectData, function (selIndex,selObj) {
                            if (selObj.selected) { 
                             if (n>0) selectedText += "|";
                             selectedText += selObj.id;
                             n++;
                            }
                        });	
                        values[theField]=selectedText;
                    }
                    else values[theField]=jQuery(this.formElement).find(`input[name="${theField}"]`).val();                    
                } 
                return values;
            }
        },
        validateForm: (checkedForm) => { 
            let isOk=true;                      
            for (let key in majaxViewComponents.mForms.postedFields) {
                let name=majaxViewComponents.mForms.postedFields[key]["name"];
                let checkType=majaxViewComponents.mForms.postedFields[key]["type"];
                let checkedElement=null;
                if (checkType == "textarea") checkedElement=jQuery(checkedForm).find('textarea[name="'+name+'"]');
                else checkedElement=jQuery(checkedForm).find('input[name="'+name+'"]');
                let val = jQuery(checkedElement).val();
                let mErr=majaxViewComponents.mForms.check(key,val);
                let prev=jQuery(checkedElement).prev();
                if (mErr !== true) {                    
                    if (jQuery(prev).data('formerr')=="1") jQuery(prev).text(mErr);
                    else jQuery('<span class="formerr" data-formerr="1">'+mErr+'</span>').insertBefore(checkedElement);
                    isOk=false;
                } else {
                   if (jQuery(prev).data('formerr')=="1") jQuery(prev).text("");                   
                }                           
            }
            return isOk;
        }
    }
     
    

    my.majaxViewComponents=majaxViewComponents;
    return my;

 }(majaxModule || {} ));