(() => {
 const feedback=document.getElementById('settings-feedback');if(feedback){feedback.focus({preventScroll:true});}
 // No fields are disabled/hidden: non-JS setup and all authentication modes remain accessible.
 const preset=document.querySelector('[data-email-preset]');preset?.addEventListener('change',()=>{if(!preset.value)return;const form=preset.form;form.elements.smtp_host.value=preset.value==='personal'?'smtp-mail.outlook.com':'smtp.office365.com';form.elements.smtp_port.value='587';form.elements.smtp_security.value='starttls';form.elements.smtp_auth.value='microsoft';form.elements.ms_mail_tenant.value=preset.value==='personal'?'consumers':'organizations';});
})();
