
var onReCaptchaLoad = function () {
	//tohle je kvuli tomu kdyz se zada odkaz primo
		
}

var majaxModule=(function (my) {


const mStrings = {
	mReplaceAll: (mStr,from,to) => {
		return mStr.split(from).join(to); //replaceAll added on August2020
	},
	mNormalize: (mStr) => {
		let newStr="" + mStr;
		newStr=newStr.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
			return (mStrings.mReplaceAll(newStr," ","-"));		 
	},
	isNonEmptyStr: (mVar) => {
		if ((typeof mVar === 'string' || mVar instanceof String) && mVar) return true;
		return false;
	},
	stringOrDefault: (mVar,mDefault="") => {
		if ((typeof mVar === 'string' || mVar instanceof String) && mVar) return mVar;
		return mDefault;
	}
}



const mCounts = {
	metaCounts: [],
	clearAll: function() {		
		for (let key in this.metaCounts) {
			this.metaCounts[key]["rowsCount"]=0;
		}
	},
	addMetaCount: function(mkey,mname,mcnt) {
	  let key=this.getMetaRowKey(mkey,mname);
	  if (!key) {
		  this.metaCounts.push({
			  metaKey: mkey,
			  metaName: mname,
			  rowsCount: mcnt
		  })
	  } else {
		  this.metaCounts[key]["metaKey"]=mkey;
		  this.metaCounts[key]["metaName"]=mname;
		  this.metaCounts[key]["rowsCount"]=mcnt;
	  }	
	},
	getMetaRowKey: function(mkey,mname) {
	  for (let key in this.metaCounts) {
		  if (this.metaCounts[key]["metaKey"]==mkey && this.metaCounts[key]["metaName"]==mname) return key;
	  }
	  return false;
	},
	getMetaCnt: function(mkey,mname) {
		let key=this.getMetaRowKey(mkey,mname);
		if (!key) {
			return "0";
		}
		return this.metaCounts[key]["rowsCount"]=this.metaCounts[key]["rowsCount"];
	  }
	  
}
const metaMisc = {
	icons: [],
	valMin: [],
	valMax: [],
	fieldformat: [],
	aktMin:[],
	aktMax:[],
	displayorder:[],
	htmlTemplate:[],
	virtVal:[],
	title:[],
	addMetaMisc: function(misc) {
		for (let key in misc) {
			this.icons[key]=misc[key]["icon"];
			this.valMin[key]=misc[key]["min"];
			this.valMax[key]=misc[key]["max"];
			if (this.valMax[key]>0 && this.valMin[key]==this.valMax[key]) {
				this.valMin[key]=0;
			}
			this.fieldformat[key]=misc[key]["fieldformat"];
			this.displayorder[key]=misc[key]["displayorder"];
			this.title[key]=misc[key]["title"];
			this.htmlTemplate[key]=misc[key]["htmlTemplate"];
			this.virtVal[key]=misc[key]["virtVal"];
		}
	},
	formatMetaVal: (val1 , val2=0, format=2, direction="toFormat", oneVal=false) => {		
		if (direction=="toFormat") {			
			if (oneVal) { 
				if (format.indexOf("%1") !== -1) return format.replace("%1",val1);
				else return val1;
			}
			if (format===2) return ""	+ val1 + " - " + "" + val2 + "";
			else {
			 return format.replace("%1",val1) + " - " + format.replace("%1",val2);
			}
		}
		else {
			if (format!==2) {
				let formatStr=format.replace("%1","");
				val1=val1.split(formatStr).join(""); //replaceAll added on August2020
				val1=val1.split(" - ").join("|"); //or use replaceAll
				return val1;
			}
			//convert from format			
			//let's take 2 numbers
			const rex = /-?\d(?:[,\d]*\.\d+|[,\d]*)/g;
			let out="";
			while ((match = rex.exec(val1)) !== null) {
			if (out!="") out+='|'; 
			out+=match[0];
			}
			return out;
		}
    }
};

	
jQuery(document).ready(function($) {	
	let hasFilterForm=false;
	let hasIdSign=false;
	let instaLoad=false;
	if ($('#majaxform').length>0)  hasFilterForm=true;
	if ($('#idSign').length>0)  hasIdSign=true;
	//handle static forms
	let staticForms=jQuery('form[data-group="staticForms"]');
	staticForms.each(function (i,obj) {
		my.majaxPrc.runAjax(obj,"formInit");
	});

	my.mUrl.readUrl();
	//fire event handlers			
	$('.majax-select').on('change', function() {				
			my.majaxPrc.runAjax(this);
	});
	$('.majax-fireinputs').on('change', function() {	
			my.majaxPrc.runAjax(this);
	});		
	//select2
	let selects2 =$(".majax-select");
	if (selects2.length>0) {
		selects2.select2({
			templateResult: my.majaxSelect.formatState,
			templateSelection: my.majaxSelect.formatState
		});
	}		

	//click items anchors 
	$('#majaxmain').on('click', 'a', function(event) {
		let rel=$(this).attr('rel');
		if (rel=="nofollow") return true;
		let noajax=$(this).attr('data-noajax');
		if (noajax=="true") return true;
		let href=$(this).attr('href');
		//console.info('Anchor clicked!' + href);
		//window.history.pushState({href: href}, '', href);
		my.mUrl.saveUrl();
		window.history.pushState({href: href}, '', href);
		my.majaxPrc.runAjax(this);
		event.preventDefault();			
		return false;
	});
	
	$('#goBackButton').on('click', function(e) {			
		e.stopImmediatePropagation();
		my.mUrl.goBack();			
	});

	$(window).scroll(function() {		
		//return true;
		let footerHeight=150;
		if($(window).scrollTop() + $(window).height() >= $("#majaxmain").height() + 20) {			
			my.majaxPrc.runAjax(this,"scroll");
		}
	 });
	
	window.addEventListener('popstate', function(e){
		let href="";
		if(e.state) href=e.state.href;
		//reset filter boxes
		if (hasFilterForm) {
			my.majaxSelect.resetAll();
			my.majaxPrc.runAjax(this);			
		} else {				
				location.reload(); 				
		}	
		}); 
		
	//sliders
	my.majaxSlider.initSliders(); 
	
	//load
	if ((instaLoad) && (hasFilterForm || hasIdSign)) my.majaxPrc.runAjax(false);
}); 



my.mStrings=mStrings;
my.metaMisc=metaMisc;
my.mCounts=mCounts;
my.metaMisc=metaMisc;
return my;

}(majaxModule || {} ));