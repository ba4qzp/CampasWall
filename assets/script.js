(function(){
    function esc(s){ var d=document.createElement('div'); d.textContent=s==null?'':String(s); return d.innerHTML; }
    window.escHtml = esc;

    window.renderMarkdown = function(text){
        if(!text) return '';
        if(typeof marked === 'undefined') return esc(text).replace(/\n/g,'<br>');
        try{
            var html = marked.parse(String(text), {breaks:true, gfm:true});
            html = html.replace(/<script[\s\S]*?<\/script>/gi, '').replace(/\son\w+\s*=\s*"[^"]*"/gi, '');
            html = html.replace(/(href|src)="([^"]*)"/gi, function(m, attr, url){
                if(/^(https?:|\/\/|\/|#|mailto:|data:)/i.test(url)) return m;
                return attr + '="/' + url + '"';
            });
            html = html.replace(/@(\d{1,9})/g, '<a href="/profile.php?uid=$1" style="color:#2563eb;background:#dbeafe;padding:1px 5px;border-radius:3px;">@$1</a>');
            return html;
        }catch(e){ return esc(text).replace(/\n/g,'<br>'); }
    };
    
    window.mdRenderAll = function(){
        document.querySelectorAll('.md').forEach(function(el){
            if(el.dataset.mdDone === '1') return;
            el.innerHTML = window.renderMarkdown(el.textContent);
            el.dataset.mdDone = '1';
        });
    };
    if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window.mdRenderAll);
    else window.mdRenderAll();
})();
