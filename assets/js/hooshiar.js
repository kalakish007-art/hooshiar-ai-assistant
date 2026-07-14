// هوشیار — Frontend Chat Widget
(function(){
    const d = hooshiarData;
    let isOpen = false, isProcessing = false;
    let history = [];

    const toggle = document.getElementById('hooshiar-toggle');
    const box  = document.getElementById('hooshiar-box');
    const close = document.getElementById('hooshiar-close');
    const msgs  = document.getElementById('hooshiar-messages');
    const input = document.getElementById('hooshiar-input');
    const send  = document.getElementById('hooshiar-send');
    const searchBtn = document.getElementById('hooshiar-search-btn');

    function open(){box.style.display='flex';isOpen=true;toggle.style.display='none';scrollDown();input.focus()}
    function shut(){box.style.display='none';isOpen=false;toggle.style.display='flex'}
    
    toggle.onclick = open;
    close.onclick = shut;
    searchBtn.onclick = function(){input.value='جستجو کن: ';open();input.focus()};

    function scrollDown(){msgs.scrollTop=msgs.scrollHeight}

    function addMsg(text, role){
        const el = document.createElement('div');
        el.className = 'hooshiar-msg ' + role;
        el.innerHTML = text.replace(/\n/g,'<br>').replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>');
        msgs.appendChild(el);
        scrollDown();
        return el;
    }

    function addTyping(){
        const el = document.createElement('div');
        el.className = 'hooshiar-msg typing';
        el.id = 'hooshiar-typing';
        el.textContent = 'هوشیار در حال فکر کردن... ✨';
        msgs.appendChild(el);
        scrollDown();
        return el;
    }

    function removeTyping(){
        const el = document.getElementById('hooshiar-typing');
        if(el) el.remove();
    }

    function showGreeting(){
        msgs.innerHTML = '';
        addMsg(d.greeting || 'سلام! 👋 من هوشیارم. بپرس! 😊', 'agent');
    }

    async function sendMessage(){
        const msg = input.value.trim();
        if(!msg || isProcessing) return;
        isProcessing = true;
        send.disabled = true;
        input.value = '';

        addMsg(msg, 'user');
        history.push({role:'user', text:msg});
        const typing = addTyping();

        try {
            const form = new FormData();
            form.append('action','hooshiar_chat');
            form.append('nonce',d.nonce);
            form.append('message',msg);
            form.append('history',JSON.stringify(history.slice(-10)));

            const resp = await fetch(d.ajaxUrl, {method:'POST',body:form});
            const data = await resp.json();
            removeTyping();

            if(data.success && data.data.text){
                addMsg(data.data.text, 'agent');
                history.push({role:'assistant', text:data.data.text});
            } else {
                addMsg(data.data?.text || 'خطا در ارتباط. دوباره تلاش کن 😊', 'agent');
            }
        } catch(e){
            removeTyping();
            addMsg('⚠️ خطا در ارتباط با سرور. لطفاً دوباره تلاش کن.', 'agent');
        }

        isProcessing = false;
        send.disabled = false;
        input.focus();
        if(history.length > 20) history = history.slice(-20);
    }

    send.onclick = sendMessage;
    input.onkeydown = function(e){if(e.key==='Enter' && !e.shiftKey){e.preventDefault();sendMessage()}};

    // Show greeting on first open
    toggle.onclick = function(){
        open();
        if(msgs.children.length === 0) showGreeting();
    };

    // Re-attach toggle click if it was already shown
    if(msgs.children.length === 0) showGreeting();
})();
