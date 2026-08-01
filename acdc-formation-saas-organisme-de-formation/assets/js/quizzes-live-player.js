/**
 * ACDC Formation SAAS — Quiz Live Player JS (3.21.04.2-b)
 * Fixes : qcm_multiple, poll, open_text, puzzle + leaderboard NaN
 */
(function () {
    'use strict';

    var root = document.querySelector('.acdc-qz-live-player');
    if (!root) { return; }

    var ajaxUrl = root.dataset.ajaxUrl;
    var POLL_INTERVAL = 1500;
    var pollTimer = null;
    var state = {
        current: 'join', participantId: 0, token: '', sessionId: 0,
        nickname: '', avatar: '', currentQuestionId: 0, questionStartedAt: 0,
        answeredCurrentQuestion: false, podiumAnimated: false, soundEnabled: true,
        currentQuestionType: '',
        puzzleSlots: [],   // [answerId or null] par position
        puzzleBankIds: [], // ids dans le bank
        selectedPuzzleTileId: null,
        antifraud: { no_back: false, visibility_check: false, time_limit: false, fullscreen: false },
        selectedLearnerId: 0, // 0 = invité anonyme, >0 = apprenant identifié
    };

    // ====== BLOCAGE NAVIGATION ARRIÈRE ====================================
    // S'active après join ET uniquement si antifraud.no_back est actif
    var backBlocked = false;
    function enableBackBlock() {
        if (backBlocked) return;
        if (!state.antifraud.no_back) return;
        backBlocked = true;
        history.pushState(null, '', location.href);
        window.addEventListener('popstate', function() {
            history.pushState(null, '', location.href);
        });
    }

    // ====== OVERLAY ANTI-TRICHE ==========================================
    // Overlay partagé pour les avertissements fullscreen et tab-switch
    function showAntifraudOverlay(msg) {
        var ov = document.getElementById('acdc-qz-antifraud-overlay');
        if (!ov) {
            ov = document.createElement('div');
            ov.id = 'acdc-qz-antifraud-overlay';
            ov.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999;background:rgba(15,44,82,.92);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:32px;';
            ov.innerHTML = '<div style="background:#fff;border-radius:16px;padding:32px 40px;max-width:480px;box-shadow:0 8px 40px rgba(0,0,0,.35);">'
                + '<div style="font-size:48px;margin-bottom:12px;">\u26a0\ufe0f</div>'
                + '<div id="acdc-qz-antifraud-msg" style="font-size:17px;font-weight:700;color:#0f2c52;margin-bottom:16px;"></div>'
                + '<div style="font-size:13px;color:#6b7280;margin-bottom:24px;">Cet incident a \u00e9t\u00e9 enregistr\u00e9.</div>'
                + '<button id="acdc-qz-antifraud-close" style="background:#d6a353;color:#0f2c52;font-weight:800;font-size:15px;border:none;border-radius:10px;padding:12px 28px;cursor:pointer;">Continuer</button>'
                + '</div>';
            document.body.appendChild(ov);
            document.getElementById('acdc-qz-antifraud-close').addEventListener('click', function() {
                ov.style.display = 'none';
                if (state.antifraud.fullscreen) { tryRequestFullscreen(); }
            });
        }
        document.getElementById('acdc-qz-antifraud-msg').textContent = msg;
        ov.style.display = 'flex';
    }

    // ====== PLEIN ECRAN (antifraud_fullscreen) ============================
    function tryRequestFullscreen() {
        var el = document.documentElement;
        try {
            if (el.requestFullscreen) { el.requestFullscreen(); }
            else if (el.webkitRequestFullscreen) { el.webkitRequestFullscreen(); }
            else if (el.mozRequestFullScreen) { el.mozRequestFullScreen(); }
        } catch(e) {}
    }

    document.addEventListener('fullscreenchange', function() {
        if (!state.antifraud.fullscreen) return;
        if (!document.fullscreenElement && state.participantId && state.current === 'question') {
            showAntifraudOverlay('Vous avez quitt\u00e9 le mode plein \u00e9cran.');
        }
    });
    document.addEventListener('webkitfullscreenchange', function() {
        if (!state.antifraud.fullscreen) return;
        if (!document.webkitFullscreenElement && state.participantId && state.current === 'question') {
            showAntifraudOverlay('Vous avez quitt\u00e9 le mode plein \u00e9cran.');
        }
    });

    // ====== DETECTION TAB-SWITCH ==========================================
    // Ping serveur + overlay visible si antifraud.visibility_check est actif
    document.addEventListener('visibilitychange', function() {
        if (document.hidden && state.participantId && state.current === 'question' && state.antifraud.visibility_check) {
            var body = new URLSearchParams();
            body.append('action', 'acdc_of_qz_player_tab_switch');
            body.append('participant_id', state.participantId);
            body.append('token', state.token);
            fetch(ajaxUrl, {method:'POST', body:body, credentials:'same-origin'}).catch(function(){});
        }
        if (!document.hidden && state.participantId && state.current === 'question' && state.antifraud.visibility_check) {
            showAntifraudOverlay('Vous avez quitt\u00e9 l\'onglet du quiz.');
        }
    });

    // ====== TIMER PLAYER ==================================================
    var playerTimerRaf = null;
    var playerTimerData = { startedAt: 0, limit: 0, active: false };

    function startPlayerTimer(elapsedSec, limitSec) {
        stopPlayerTimer();
        var wrap = document.getElementById('acdc-qz-player-timer-wrap');
        if (!limitSec || limitSec <= 0) { if (wrap) wrap.style.display = 'none'; return; }
        if (wrap) wrap.style.display = 'block';
        playerTimerData.startedAt = Date.now() - (elapsedSec * 1000);
        playerTimerData.limit  = limitSec * 1000;
        playerTimerData.active = true;
        tickPlayerTimer();
    }

    function stopPlayerTimer() {
        playerTimerData.active = false;
        if (playerTimerRaf) { cancelAnimationFrame(playerTimerRaf); playerTimerRaf = null; }
    }

    function tickPlayerTimer() {
        if (!playerTimerData.active) return;
        var elapsed   = Date.now() - playerTimerData.startedAt;
        var remaining = Math.max(0, playerTimerData.limit - elapsed);
        var pct       = remaining / playerTimerData.limit * 100;
        var secLeft   = Math.ceil(remaining / 1000);
        var bar   = document.getElementById('acdc-qz-player-timer-bar');
        var count = document.getElementById('acdc-qz-player-timer-count');
        if (bar)   { bar.style.width = pct + '%'; bar.classList.toggle('is-urgent', pct <= 25); }
        if (count) { count.textContent = secLeft + 's'; count.classList.toggle('is-urgent', pct <= 25); }
        // Ticks sonores sur les 3 dernières secondes
        if (remaining > 0 && secLeft <= 3 && secLeft !== (playerTimerData.lastTick || -1)) {
            playerTimerData.lastTick = secLeft;
            playSound('tick');
        }
        if (remaining > 0) {
            playerTimerRaf = requestAnimationFrame(tickPlayerTimer);
        } else {
            stopPlayerTimer();
            playSound('time_up');
            if (state.antifraud.time_limit && !state.answeredCurrentQuestion) {
                // Auto-submit : forcer l'envoi de la sélection courante (ou vide si rien sélectionné)
                autoSubmitOnTimeout();
            } else {
                // Timer expiré sans auto-submit : bloquer les boutons uniquement
                document.querySelectorAll(
                    '.acdc-qz-player-answer-btn, #acdc-qz-player-open-submit, #acdc-qz-player-multi-submit, #acdc-qz-player-puzzle-submit'
                ).forEach(function(btn) { btn.disabled = true; });
            }
        }
    }

    // ====== AUTO-SUBMIT ON TIMEOUT (antifraud_time_limit) =================
    function autoSubmitOnTimeout() {
        state.answeredCurrentQuestion = true;
        // Désactiver tous les boutons de réponse
        document.querySelectorAll(
            '.acdc-qz-player-answer-btn, #acdc-qz-player-open-submit, #acdc-qz-player-multi-submit, #acdc-qz-player-puzzle-submit'
        ).forEach(function(btn) { btn.disabled = true; });

        var type = state.currentQuestionType;
        var answerIds = [];
        var answerText = '';
        var responseMs = Date.now() - state.questionStartedAt;

        if (type === 'qcm_multiple' || type === 'poll') {
            // Collecter les réponses déjà cochées
            document.querySelectorAll('.acdc-qz-player-answer-btn[data-selected="1"]').forEach(function(b) {
                answerIds.push(parseInt(b.dataset.answerId, 10));
            });
        } else if (type === 'open_text') {
            answerText = (document.getElementById('acdc-qz-player-open-text') || {}).value || '';
            answerText = answerText.trim();
        } else if (type === 'puzzle') {
            answerIds = state.puzzleSlots.filter(function(s) { return s !== null; });
            if (answerIds.length !== state.puzzleSlots.length) {
                // Puzzle incomplet : soumettre vide
                answerIds = [];
            }
        } else {
            // qcm_single, true_false : soumettre la sélection active
            var sel = document.querySelector('.acdc-qz-player-answer-btn.is-selected');
            if (sel) answerIds.push(parseInt(sel.dataset.answerId, 10));
        }

        submitAnswer(answerIds, answerText, responseMs);
    }

    // Reprise session localStorage — uniquement si la session n'est pas terminée
    // (évite de relancer le podium à chaque refresh en navigation privée)
    try {
        var saved = JSON.parse(localStorage.getItem('acdc_qz_live_session')||'null');
        if (saved && saved.participantId && saved.token) {
            state.participantId=saved.participantId; state.token=saved.token;
            state.sessionId=saved.sessionId; state.nickname=saved.nickname; state.avatar=saved.avatar;
            enableBackBlock();
            startPolling();
        }
    } catch(e){}

    // ====== SONS ==========================================================
    function playSound(type) {
        if (!state.soundEnabled) return;
        try {
            var ctx = new (window.AudioContext||window.webkitAudioContext)();
            if (type==='correct') {
                [523,659,784].forEach(function(f,i){
                    var o=ctx.createOscillator(),g=ctx.createGain();
                    o.connect(g);g.connect(ctx.destination);o.frequency.value=f;o.type='sine';
                    var t=ctx.currentTime+i*0.12;
                    g.gain.setValueAtTime(0.25,t);g.gain.exponentialRampToValueAtTime(0.001,t+0.28);
                    o.start(t);o.stop(t+0.28);
                });
            } else if (type==='wrong') {
                var o=ctx.createOscillator(),g=ctx.createGain();
                o.connect(g);g.connect(ctx.destination);
                o.frequency.setValueAtTime(280,ctx.currentTime);o.frequency.exponentialRampToValueAtTime(140,ctx.currentTime+0.35);
                o.type='sawtooth';
                g.gain.setValueAtTime(0.18,ctx.currentTime);g.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+0.4);
                o.start(ctx.currentTime);o.stop(ctx.currentTime+0.4);
            } else if (type==='podium') {
                [523,659,784,1046].forEach(function(f,i){
                    var o=ctx.createOscillator(),g=ctx.createGain();
                    o.connect(g);g.connect(ctx.destination);o.frequency.value=f;o.type='sine';
                    var t=ctx.currentTime+i*0.18;
                    g.gain.setValueAtTime(0.2,t);g.gain.exponentialRampToValueAtTime(0.001,t+0.45);
                    o.start(t);o.stop(t+0.45);
                });
            } else if (type==='tick') {
                var o=ctx.createOscillator(),g=ctx.createGain();
                o.connect(g);g.connect(ctx.destination);
                o.frequency.value=1200;o.type='square';
                g.gain.setValueAtTime(0.07,ctx.currentTime);g.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+0.07);
                o.start(ctx.currentTime);o.stop(ctx.currentTime+0.07);
            } else if (type==='time_up') {
                var o=ctx.createOscillator(),g=ctx.createGain();
                o.connect(g);g.connect(ctx.destination);
                o.frequency.setValueAtTime(600,ctx.currentTime);o.frequency.exponentialRampToValueAtTime(200,ctx.currentTime+0.4);
                o.type='sawtooth';
                g.gain.setValueAtTime(0.15,ctx.currentTime);g.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+0.45);
                o.start(ctx.currentTime);o.stop(ctx.currentTime+0.45);
            }
        } catch(e){}
    }

    // ====== AJAX ==========================================================
    function ajax(action, params, cb) {
        var body = new URLSearchParams();
        body.append('action', action);
        Object.keys(params||{}).forEach(function(k){body.append(k,params[k]);});
        fetch(ajaxUrl,{method:'POST',body:body,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){cb&&cb(j);}).catch(function(e){cb&&cb({success:false,data:{message:e.message}});});
    }

    // ====== STATE MACHINE =================================================
    function showState(name) {
        if (state.current === name) return;
        state.current = name;
        root.querySelectorAll('.acdc-qz-player-state').forEach(function(el){
            el.classList.toggle('is-active', el.dataset.state===name);
        });
    }

    // ====== AVATAR CARDS ==================================================
    root.querySelectorAll('.acdc-qz-player-avatar-card-big').forEach(function(label){
        label.addEventListener('click', function(){
            root.querySelectorAll('.acdc-qz-player-avatar-card-big').forEach(function(l){l.classList.remove('is-selected');});
            label.classList.add('is-selected');
            var radio = label.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        });
    });

    // ====== JOIN ==========================================================
    var joinBtn = document.getElementById('acdc-qz-player-join-btn');
    if (joinBtn) joinBtn.addEventListener('click', handleJoin);

    // Déclencher la recherche des apprenants quand le PIN est complet
    var pinInput = document.getElementById('acdc-qz-player-pin');
    var learnerPickEl = document.getElementById('acdc-qz-player-learner-pick');
    var nicknameWrapEl = document.getElementById('acdc-qz-player-nickname-wrap');
    var lastCheckedPin = '';

    function loadLearnersForPin(pin) {
        if (pin === lastCheckedPin) return;
        lastCheckedPin = pin;
        if (pin.length < 4) {
            if (learnerPickEl) learnerPickEl.style.display = 'none';
            if (nicknameWrapEl) nicknameWrapEl.style.display = 'none';
            return;
        }
        ajax('acdc_of_qz_get_session_learners', {pin: pin}, function(j) {
            if (!j.success) {
                if (learnerPickEl) learnerPickEl.style.display = 'none';
                if (nicknameWrapEl) { nicknameWrapEl.style.display = ''; }
                return;
            }
            var learners = j.data.learners || [];
            if (learners.length === 0) {
                // Pas d'apprenants → formulaire classique
                if (learnerPickEl) learnerPickEl.style.display = 'none';
                if (nicknameWrapEl) nicknameWrapEl.style.display = '';
                return;
            }
            // Afficher les cartes apprenants
            var listEl = document.getElementById('acdc-qz-player-learner-list');
            listEl.innerHTML = '';
            learners.forEach(function(l) {
                var card = document.createElement('button');
                card.type = 'button';
                card.className = 'acdc-qz-learner-card';
                card.dataset.learnerId = l.id;
                card.dataset.firstName = l.first_name;
                card.dataset.lastName  = l.last_name;
                card.innerHTML = '<span style="font-weight:700;font-size:15px;color:#0f2c52;">'
                    + esc(l.first_name) + '</span><br>'
                    + '<span style="font-size:12px;color:#4b5d76;">'
                    + esc(l.last_name) + '</span>';
                card.style.cssText = 'padding:12px 18px;border-radius:10px;border:2px solid #e8ecf0;background:#fff;cursor:pointer;text-align:center;min-width:110px;transition:all .15s;';
                card.addEventListener('click', function() {
                    // Désélectionner toutes les cartes
                    listEl.querySelectorAll('.acdc-qz-learner-card').forEach(function(c) {
                        c.style.borderColor = '#e8ecf0';
                        c.style.background  = '#fff';
                    });
                    // Sélectionner cette carte
                    card.style.borderColor = '#d6a353';
                    card.style.background  = '#fef6e4';
                    // Pré-remplir le nickname avec le prénom
                    var nicknameInput = document.getElementById('acdc-qz-player-nickname-input');
                    if (nicknameInput) nicknameInput.value = l.first_name;
                    state.selectedLearnerId = parseInt(l.id, 10);
                    // Afficher le champ pseudo pour permettre la modification
                    if (nicknameWrapEl) nicknameWrapEl.style.display = '';
                });
                listEl.appendChild(card);
            });
            if (learnerPickEl) learnerPickEl.style.display = '';
            if (nicknameWrapEl) nicknameWrapEl.style.display = 'none'; // masqué jusqu'à sélection
        });
    }

    if (pinInput) {
        // Déclencher au chargement si PIN pré-rempli depuis URL
        if (pinInput.value) loadLearnersForPin(pinInput.value.replace(/\D/g,''));
        pinInput.addEventListener('input', function() {
            loadLearnersForPin(pinInput.value.replace(/\D/g,''));
        });
        pinInput.addEventListener('blur', function() {
            loadLearnersForPin(pinInput.value.replace(/\D/g,''));
        });
    }

    // Lien 'Rejoindre comme invité'
    var guestLink = document.getElementById('acdc-qz-player-guest-link');
    if (guestLink) {
        guestLink.addEventListener('click', function(e) {
            e.preventDefault();
            // Désélectionner tout apprenant
            state.selectedLearnerId = 0;
            document.querySelectorAll('.acdc-qz-learner-card').forEach(function(c) {
                c.style.borderColor = '#e8ecf0';
                c.style.background  = '#fff';
            });
            var nicknameInput = document.getElementById('acdc-qz-player-nickname-input');
            if (nicknameInput) nicknameInput.value = '';
            if (nicknameWrapEl) nicknameWrapEl.style.display = '';
        });
    }

    function handleJoin() {
        var pin      = (document.getElementById('acdc-qz-player-pin').value||'').replace(/\D/g,'');
        var nickname = (document.getElementById('acdc-qz-player-nickname-input').value||'').trim();
        var avatarInput = document.querySelector('input[name="avatar"]:checked');
        var avatar   = avatarInput ? avatarInput.value : '';
        var errorEl  = document.getElementById('acdc-qz-player-error');
        errorEl.textContent = '';
        if (pin.length < 4)  { errorEl.textContent = 'PIN invalide.'; return; }
        if (!nickname)        { errorEl.textContent = 'Choisis un pseudo.'; return; }
        if (!avatar)          { errorEl.textContent = 'Choisis un avatar.'; return; }
        joinBtn.disabled = true;
        joinBtn.textContent = 'Connexion…';
        var waiting = document.getElementById('acdc-qz-player-waiting-inline');
        if (waiting) waiting.style.display = 'flex';
        var joinParams = {pin:pin, nickname:nickname, avatar:avatar};
        if (state.selectedLearnerId > 0) joinParams.learner_id = state.selectedLearnerId;
        ajax('acdc_of_qz_player_join', joinParams, function(j){
            joinBtn.disabled = false;
            joinBtn.textContent = 'REJOINDRE LA PARTIE';
            if (!j.success) { errorEl.textContent=(j.data&&j.data.message)||'Erreur'; if(waiting) waiting.style.display='none'; return; }
            state.participantId=j.data.participant_id; state.token=j.data.token;
            state.sessionId=j.data.session_id; state.nickname=nickname; state.avatar=avatar;
            try { localStorage.setItem('acdc_qz_live_session', JSON.stringify({participantId:state.participantId,token:state.token,sessionId:state.sessionId,nickname:state.nickname,avatar:state.avatar})); } catch(e){}
            var nickEl = document.getElementById('acdc-qz-player-nickname');
            if (nickEl) nickEl.textContent = nickname;
            enableBackBlock();
            startPolling();
        });
    }

    // ====== POLLING =======================================================
    function startPolling() {
        if (pollTimer) return;
        showState('waiting');
        poll();
        pollTimer = setInterval(poll, POLL_INTERVAL);
    }

    function poll() {
        if (!state.participantId||!state.token) return;
        ajax('acdc_of_qz_player_poll',{participant_id:state.participantId,token:state.token},function(j){
            if (!j.success) {
                // Effacer le localStorage et recharger sur toute erreur serveur
                // (session expirée, participant introuvable, token invalide, etc.)
                try { localStorage.removeItem('acdc_qz_live_session'); } catch(e){}
                location.reload();
                return;
            }
            if (!j.data) { return; }
            updateFromPoll(j.data);
        });
    }

    function updateFromPoll(data) {
        if (!data) return;
        // Stocker les flags anti-triche dès qu'ils arrivent
        if (data.antifraud) {
            state.antifraud = data.antifraud;
        }
        if (data.me) {
            // Initialiser runningScore depuis le serveur si pas encore initialisé
            if (state.runningScore === undefined || state.runningScore === null) {
                state.runningScore = parseFloat(data.me.score) || 0;
            }
            var scoreEl = document.getElementById('acdc-qz-player-score');
            if (scoreEl) scoreEl.textContent = Math.round(state.runningScore) + ' pts';
        }
        if (data.status === 'lobby') {
            showState('waiting');
        } else if (data.status === 'in_progress') {
            var qid = data.current_q_id || 0;
            if (qid && qid !== state.currentQuestionId) {
                state.currentQuestionId = qid;
                state.questionStartedAt = Date.now();
                state.answeredCurrentQuestion = false;
                var tl = (data.current_question && data.current_question.time_limit) ? parseInt(data.current_question.time_limit, 10) : 0;
                startPlayerTimer(data.current_q_elapsed_seconds || 0, tl);
                renderQuestion(data);
                showState('question');
                // Anti-triche : activer no_back et plein écran à la première question
                enableBackBlock();
                if (state.antifraud.fullscreen && !document.fullscreenElement && !document.webkitFullscreenElement) {
                    tryRequestFullscreen();
                }
            }
        } else if (data.status === 'ended') {
            if (!state.podiumAnimated) {
                state.podiumAnimated = true;
                renderPodiumPlayer(data);
                playSound('podium');
                // Effacer la session après 15s — prochain refresh = formulaire vierge
                setTimeout(function(){
                    try { localStorage.removeItem('acdc_qz_live_session'); } catch(e){}
                }, 15000);
            }
            showState('podium');
        }
    }

    // ====== RENDU QUESTION — par type =====================================
    function renderQuestion(data) {
        var q = data.current_question;
        if (!q) return;
        state.currentQuestionType = q.type;

        document.getElementById('acdc-qz-player-q-title').textContent = q.title;
        document.getElementById('acdc-qz-player-q-num').textContent = (data.current_q_num||'?') + '/' + (data.total_q||'?');
        document.getElementById('acdc-qz-player-q-type').textContent = q.type_label||'';

        // Cacher tous les blocs de saisie
        var answersEl   = document.getElementById('acdc-qz-player-q-answers');
        var openWrap    = document.getElementById('acdc-qz-player-open-wrap');
        var puzzleWrap  = document.getElementById('acdc-qz-player-puzzle-wrap');
        var multiFooter = document.getElementById('acdc-qz-player-multi-footer');
        answersEl.style.display   = '';
        openWrap.style.display    = 'none';
        puzzleWrap.style.display  = 'none';
        multiFooter.style.display = 'none';
        answersEl.innerHTML = '';
        document.getElementById('acdc-qz-player-open-text').value = '';

        if (q.type === 'open_text') {
            renderOpenText(q, data);
        } else if (q.type === 'puzzle') {
            renderPuzzle(q, data);
        } else if (q.type === 'qcm_multiple' || q.type === 'poll') {
            renderMultiChoice(q, data);
        } else {
            // qcm_single, true_false
            renderSingleChoice(q, data);
        }
    }

    // --- Choix unique ---
    function renderSingleChoice(q, data) {
        var letters = ['A','B','C','D','E','F'];
        var html = '';
        (q.answers||[]).forEach(function(a,i){
            html += '<button type="button" class="acdc-qz-player-answer-btn acdc-qz-host-answer-tile-' + i + '" data-answer-id="' + a.id + '">'
                  + '<span class="acdc-qz-player-answer-letter">' + (letters[i]||(i+1)) + '</span>'
                  + '<span class="acdc-qz-player-answer-label">' + esc(a.label) + '</span>'
                  + '</button>';
        });
        var answersEl = document.getElementById('acdc-qz-player-q-answers');
        answersEl.innerHTML = html;
        answersEl.querySelectorAll('.acdc-qz-player-answer-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                if (state.answeredCurrentQuestion) return; // verrou souple: on peut re-cliquer
                var responseMs = Date.now() - state.questionStartedAt;
                answersEl.querySelectorAll('.acdc-qz-player-answer-btn').forEach(function(b){b.classList.remove('is-selected');});
                btn.classList.add('is-selected');
                state.answeredCurrentQuestion = true;
                submitAnswer([parseInt(btn.dataset.answerId,10)], '', responseMs);
            });
        });
    }

    // --- Choix multiple (qcm_multiple, poll) ---
    function renderMultiChoice(q, data) {
        var letters = ['A','B','C','D','E','F'];
        var html = '';
        (q.answers||[]).forEach(function(a,i){
            html += '<button type="button" class="acdc-qz-player-answer-btn acdc-qz-host-answer-tile-' + i + '" data-answer-id="' + a.id + '" data-selected="0">'
                  + '<span class="acdc-qz-player-answer-letter">' + (letters[i]||(i+1)) + '</span>'
                  + '<span class="acdc-qz-player-answer-label">' + esc(a.label) + '</span>'
                  + '</button>';
        });
        var answersEl = document.getElementById('acdc-qz-player-q-answers');
        answersEl.innerHTML = html;
        var multiFooter = document.getElementById('acdc-qz-player-multi-footer');
        multiFooter.style.display = 'flex';

        answersEl.querySelectorAll('.acdc-qz-player-answer-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                var sel = btn.dataset.selected === '1';
                btn.dataset.selected = sel ? '0' : '1';
                btn.classList.toggle('is-selected', !sel);
            });
        });

        var submitBtn = document.getElementById('acdc-qz-player-multi-submit');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Valider';
        submitBtn.onclick = null; // reset tout ancien handler
        var snapshotEl = answersEl; // snapshot stable pour le closure
        submitBtn.onclick = function(){
            var selected = [];
            snapshotEl.querySelectorAll('.acdc-qz-player-answer-btn[data-selected="1"]').forEach(function(b){
                selected.push(parseInt(b.dataset.answerId,10));
            });
            if (!selected.length) {
                submitBtn.textContent = 'Sélectionne au moins une réponse';
                setTimeout(function(){ submitBtn.textContent = 'Valider'; }, 2000);
                return;
            }
            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoyé ✓';
            var responseMs = Date.now() - state.questionStartedAt;
            submitAnswer(selected, '', responseMs);
        };
    }

    // --- Réponse libre ---
    function renderOpenText(q, data) {
        var answersEl = document.getElementById('acdc-qz-player-q-answers');
        answersEl.style.display = 'none';
        var openWrap = document.getElementById('acdc-qz-player-open-wrap');
        openWrap.style.display = 'flex';
        var submitBtn = document.getElementById('acdc-qz-player-open-submit');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Envoyer';
        submitBtn.onclick = function(){
            var text = document.getElementById('acdc-qz-player-open-text').value.trim();
            if (!text) return;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoyé ✓';
            var responseMs = Date.now() - state.questionStartedAt;
            submitAnswer([], text, responseMs);
        };
    }

    // --- Puzzle (drag & drop / click-to-place) ---
    function renderPuzzle(q, data) {
        var answersEl = document.getElementById('acdc-qz-player-q-answers');
        answersEl.style.display = 'none';
        var puzzleWrap = document.getElementById('acdc-qz-player-puzzle-wrap');
        puzzleWrap.style.display = 'flex';

        // Copie mélangée (Fisher-Yates) — chaque apprenant voit un ordre différent
        var answers = (q.answers || []).slice();
        if (answers.length > 1) {
            for (var _fi = answers.length - 1; _fi > 0; _fi--) {
                var _fj = Math.floor(Math.random() * (_fi + 1));
                var _ft = answers[_fi]; answers[_fi] = answers[_fj]; answers[_fj] = _ft;
            }
        }
        var n = answers.length;
        state.puzzleSlots    = new Array(n).fill(null);   // slot[i] = answerId placed there
        state.puzzleBankIds  = answers.map(function(a){return a.id;});
        state.selectedPuzzleTileId = null;

        // Construire les slots (colonne gauche)
        var slotsEl = document.getElementById('acdc-qz-player-puzzle-slots');
        slotsEl.innerHTML = '';
        for (var i = 0; i < n; i++) {
            var slot = document.createElement('div');
            slot.className = 'acdc-qz-puzzle-slot';
            slot.dataset.slotIdx = i;
            slot.innerHTML = '<span class="acdc-qz-puzzle-slot-num">' + (i+1) + '</span>'
                           + '<span class="acdc-qz-puzzle-slot-text">— Glissez ici —</span>';
            slot.addEventListener('click', onSlotClick);
            slotsEl.appendChild(slot);
        }

        // Construire le bank (colonne droite)
        var bankEl = document.getElementById('acdc-qz-player-puzzle-bank');
        bankEl.innerHTML = '';
        var colors = ['#feeceb','#e9f2fa','#fff4d6','#e8f4ec','#f3e5f5'];
        answers.forEach(function(a, i){
            var tile = document.createElement('div');
            tile.className = 'acdc-qz-puzzle-tile';
            tile.dataset.answerId = a.id;
            tile.style.background = colors[i % colors.length];
            tile.textContent = a.label;
            tile.setAttribute('draggable', 'true');
            tile.addEventListener('click', onTileClick);
            tile.addEventListener('dragstart', onDragStart);
            bankEl.appendChild(tile);
        });

        // Drag events sur les slots (desktop)
        slotsEl.querySelectorAll('.acdc-qz-puzzle-slot').forEach(function(slot){
            slot.addEventListener('dragover', function(e){ e.preventDefault(); slot.classList.add('is-drop-target'); });
            slot.addEventListener('dragleave', function(){ slot.classList.remove('is-drop-target'); });
            slot.addEventListener('drop', function(e){
                e.preventDefault();
                slot.classList.remove('is-drop-target');
                var aid = parseInt(e.dataTransfer.getData('text/plain'), 10);
                placeInSlot(parseInt(slot.dataset.slotIdx, 10), aid);
            });
        });

        // ---- TOUCH events pour mobile (iOS / Android) ----
        var _touchDragId = null;
        var _ghostEl = null;

        function _createGhost(tile) {
            var ghost = tile.cloneNode(true);
            ghost.id = 'acdc-puzzle-ghost';
            ghost.style.cssText = 'position:fixed;pointer-events:none;z-index:9999;opacity:0.85;border-radius:10px;padding:10px 16px;font-weight:700;font-size:15px;background:'+getComputedStyle(tile).background+';color:#0f2c52;box-shadow:0 4px 16px rgba(0,0,0,.25);white-space:nowrap;';
            document.body.appendChild(ghost);
            return ghost;
        }

        bankEl.querySelectorAll('.acdc-qz-puzzle-tile').forEach(function(tile){
            tile.addEventListener('touchstart', function(e){
                if (tile.classList.contains('is-placed')) return;
                _touchDragId = parseInt(tile.dataset.answerId, 10);
                _ghostEl = _createGhost(tile);
                var t = e.touches[0];
                _ghostEl.style.left = (t.clientX - 60) + 'px';
                _ghostEl.style.top  = (t.clientY - 25) + 'px';
                tile.style.opacity = '0.4';
                e.preventDefault();
            }, {passive:false});

            tile.addEventListener('touchmove', function(e){
                if (!_ghostEl) return;
                var t = e.touches[0];
                _ghostEl.style.left = (t.clientX - 60) + 'px';
                _ghostEl.style.top  = (t.clientY - 25) + 'px';
                // Highlight slot sous le doigt
                var el = document.elementFromPoint(t.clientX, t.clientY);
                document.querySelectorAll('.acdc-qz-puzzle-slot').forEach(function(s){ s.classList.remove('is-drop-target'); });
                var slot = el && el.closest ? el.closest('.acdc-qz-puzzle-slot') : null;
                if (slot) slot.classList.add('is-drop-target');
                e.preventDefault();
            }, {passive:false});

            tile.addEventListener('touchend', function(e){
                var t = e.changedTouches[0];
                if (_ghostEl) { _ghostEl.remove(); _ghostEl = null; }
                tile.style.opacity = '';
                document.querySelectorAll('.acdc-qz-puzzle-slot').forEach(function(s){ s.classList.remove('is-drop-target'); });
                var el = document.elementFromPoint(t.clientX, t.clientY);
                var slot = el && el.closest ? el.closest('.acdc-qz-puzzle-slot') : null;
                if (slot && _touchDragId !== null) {
                    placeInSlot(parseInt(slot.dataset.slotIdx, 10), _touchDragId);
                }
                _touchDragId = null;
                e.preventDefault();
            }, {passive:false});
        });

        var submitBtn = document.getElementById('acdc-qz-player-puzzle-submit');
        submitBtn.style.display = 'none';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Valider l\'ordre';
        submitBtn.onclick = function(){
            if (state.puzzleSlots.indexOf(null) !== -1) return;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Envoyé ✓';
            var responseMs = Date.now() - state.questionStartedAt;
            submitAnswer(state.puzzleSlots, '', responseMs);
        };
    }

    var draggedTileId = null;
    function onDragStart(e) {
        draggedTileId = parseInt(e.currentTarget.dataset.answerId, 10);
        e.dataTransfer.setData('text/plain', String(draggedTileId));
    }

    function onTileClick(e) {
        var aid = parseInt(e.currentTarget.dataset.answerId, 10);
        if (e.currentTarget.classList.contains('is-placed')) return;
        // Désélectionner tout, sélectionner ce tile
        document.querySelectorAll('.acdc-qz-puzzle-tile').forEach(function(t){ t.classList.remove('is-selected'); });
        if (state.selectedPuzzleTileId === aid) {
            state.selectedPuzzleTileId = null;
        } else {
            state.selectedPuzzleTileId = aid;
            e.currentTarget.classList.add('is-selected');
        }
    }

    function onSlotClick(e) {
        var slotIdx = parseInt(e.currentTarget.dataset.slotIdx, 10);
        if (state.selectedPuzzleTileId !== null) {
            placeInSlot(slotIdx, state.selectedPuzzleTileId);
            state.selectedPuzzleTileId = null;
        } else if (state.puzzleSlots[slotIdx] !== null) {
            // Clic sur un slot déjà rempli → retirer et remettre dans le bank
            removeFromSlot(slotIdx);
        }
    }

    function placeInSlot(slotIdx, answerId) {
        var answers = document.querySelectorAll('#acdc-qz-player-puzzle-bank .acdc-qz-puzzle-tile');
        var targetTile = null;
        answers.forEach(function(t){ if (parseInt(t.dataset.answerId,10)===answerId) targetTile=t; });
        if (!targetTile || targetTile.classList.contains('is-placed')) return;
        // Si slot déjà occupé → remettre l'ancien dans le bank
        if (state.puzzleSlots[slotIdx] !== null) {
            var prevId = state.puzzleSlots[slotIdx];
            answers.forEach(function(t){ if (parseInt(t.dataset.answerId,10)===prevId) t.classList.remove('is-placed','is-selected'); });
        }
        state.puzzleSlots[slotIdx] = answerId;
        targetTile.classList.add('is-placed');
        targetTile.classList.remove('is-selected');
        // Mettre à jour le slot UI
        var slotEl = document.querySelector('.acdc-qz-puzzle-slot[data-slot-idx="' + slotIdx + '"]');
        if (slotEl) {
            var label = targetTile.textContent;
            slotEl.classList.add('is-filled');
            slotEl.innerHTML = '<span class="acdc-qz-puzzle-slot-num">' + (slotIdx+1) + '</span>'
                             + '<span class="acdc-qz-puzzle-slot-text">' + esc(label) + '</span>';
            slotEl.addEventListener('click', onSlotClick);
            slotEl.dataset.slotIdx = slotIdx;
        }
        // Vérifier si tout est rempli
        if (state.puzzleSlots.indexOf(null) === -1) {
            document.getElementById('acdc-qz-player-puzzle-submit').style.display = 'block';
        }
    }

    function removeFromSlot(slotIdx) {
        var answerId = state.puzzleSlots[slotIdx];
        state.puzzleSlots[slotIdx] = null;
        var answers = document.querySelectorAll('#acdc-qz-player-puzzle-bank .acdc-qz-puzzle-tile');
        answers.forEach(function(t){ if (parseInt(t.dataset.answerId,10)===answerId) t.classList.remove('is-placed'); });
        var slotEl = document.querySelector('.acdc-qz-puzzle-slot[data-slot-idx="' + slotIdx + '"]');
        if (slotEl) {
            slotEl.classList.remove('is-filled');
            slotEl.innerHTML = '<span class="acdc-qz-puzzle-slot-num">' + (slotIdx+1) + '</span>'
                             + '<span class="acdc-qz-puzzle-slot-text">— Glissez ici —</span>';
            slotEl.addEventListener('click', onSlotClick);
            slotEl.dataset.slotIdx = slotIdx;
        }
        document.getElementById('acdc-qz-player-puzzle-submit').style.display = 'none';
    }

    // ====== SUBMIT =======================================================
    function submitAnswer(answerIds, answerText, responseMs) {
        stopPlayerTimer();
        ajax('acdc_of_qz_player_submit_answer',{
            participant_id: state.participantId,
            token:          state.token,
            question_id:    state.currentQuestionId,
            answer_ids:     Array.isArray(answerIds) ? answerIds.join(',') : String(answerIds),
            answer_text:    answerText,
            response_ms:    responseMs,
        },function(j){
            if (j.success) {
                // FIX 1 : compteur local — ne pas attendre le prochain poll pour le total
                var earned = j.data.score || 0;
                state.runningScore = (state.runningScore || 0) + earned;
                var scoreEl = document.getElementById('acdc-qz-player-score');
                if (scoreEl) scoreEl.textContent = Math.round(state.runningScore) + ' pts';
                showFeedback(j.data.is_correct, earned);
            }
        });
    }

    // ====== FEEDBACK ======================================================
    function showFeedback(isCorrect, scoreEarned) {
        var card  = document.getElementById('acdc-qz-player-feedback-card');
        var icon  = document.getElementById('acdc-qz-player-feedback-icon');
        var title = document.getElementById('acdc-qz-player-feedback-title');
        var score = document.getElementById('acdc-qz-player-feedback-score');
        var sub   = document.getElementById('acdc-qz-player-feedback-sub');
        card.classList.remove('is-correct','is-wrong','is-neutral');
        if (isCorrect === null) {
            card.classList.add('is-neutral');
            // FIX 5 : distinguer sondage et réponse libre
            if (state.currentQuestionType === 'poll') {
                icon.textContent = '📊';
                title.textContent = 'Réponse enregistrée !';
                score.textContent = 'Merci pour votre participation';
            } else {
                icon.textContent = '✏️';
                title.textContent = 'Réponse envoyée';
                score.textContent = 'Correction manuelle par le formateur';
            }
            if (sub) sub.textContent = '';
            playSound('correct');
        } else if (isCorrect) {
            card.classList.add('is-correct');
            icon.textContent = '✅';
            title.textContent = 'Bonne réponse !';
            score.textContent = '+' + Math.round(scoreEarned) + ' pts';
            if (sub) sub.textContent = 'Total : ' + Math.round(state.runningScore || 0) + ' pts';
            playSound('correct');
        } else {
            card.classList.add('is-wrong');
            icon.textContent = '❌';
            title.textContent = 'Mauvaise réponse';
            score.textContent = '+0 pt';
            if (sub) sub.textContent = 'Total : ' + Math.round(state.runningScore || 0) + ' pts';
            playSound('wrong');
        }
        showState('feedback');
    }

    // ====== PODIUM PLAYER =================================================
    function renderPodiumPlayer(data) {
        // Force le layout du podium en JS (même fix que le host)
        var stateEl = document.querySelector('.acdc-qz-player-state-podium');
        if (stateEl) {
            stateEl.style.cssText = 'display:flex!important;flex-direction:column!important;position:fixed!important;top:0!important;left:0!important;right:0!important;bottom:0!important;width:100vw!important;height:100vh!important;z-index:50!important;overflow:hidden!important;background:#fef8f2!important;';
            var wrap = stateEl.querySelector('.acdc-qz-podium-wrap');
            if (wrap) {
                wrap.style.cssText = 'position:relative!important;flex:1!important;min-height:0!important;width:800px!important;max-width:100%!important;margin:0 auto!important;transform:none!important;top:auto!important;bottom:auto!important;left:auto!important;right:auto!important;';
            }
        }
        var container = document.getElementById('acdc-qz-player-podium');
        var myrankEl  = document.getElementById('acdc-qz-player-podium-myrank');
        if (!container) return;

        // Fix NaN : leaderboard utilise total_score (champ DB brut), participants utilise score
        var leaderboard = data.leaderboard
            ? data.leaderboard.map(function(p){ return {id:parseInt(p.id,10), nickname:p.nickname, avatar:p.avatar, score:parseFloat(p.total_score||0), rank:p.rank}; })
            : (data.participants||[]).slice(0);
        if (!data.leaderboard) {
            leaderboard.sort(function(a,b){return (b.score||0)-(a.score||0);});
        }
        var top3 = leaderboard.slice(0,3);
        var assets = window.acdcQzPodiumAssets || {};

        // Positions gérées par CSS via classes is-homme / is-femme + @media par résolution
        // Voir quizzes-live.css section PODIUM PLAYER

        container.innerHTML = '';
        var podiumImg = document.createElement('img');
        podiumImg.src = assets['base'] || '';
        podiumImg.alt = 'Podium';
        podiumImg.className = 'acdc-qz-podium-base';
        container.appendChild(podiumImg);

        [2,1,3].forEach(function(rank){
            var p = top3[rank-1];
            var block = document.createElement('div');
            var av = (p && p.avatar === 'homme') ? 'homme' : 'femme';
            // Classe genre → CSS @media applique les positions et tailles
            block.className = 'acdc-qz-podium-block acdc-qz-podium-block-' + rank + ' is-' + av;
            block.style.width = 'auto'; // reset tout ancien style width
            if (p) {
                var avatarUrl = assets[rank+'-'+av]||'';
                block.innerHTML = '<div class="acdc-qz-podium-name">'+esc(p.nickname)+'</div>'
                    + '<div class="acdc-qz-podium-score">'+Math.round(p.score||0)+' pts</div>'
                    + '<img class="acdc-qz-podium-avatar" src="'+avatarUrl+'" alt="" />';
            }
            container.appendChild(block);
        });

        // Carte "Vous" — score local (runningScore) fiable même si total_score DB est décalé
        var myRank = null;
        var myScore = Math.round(state.runningScore || (data.me ? (data.me.score||0) : 0));
        leaderboard.forEach(function(p,i){ if (p.id === state.participantId) myRank = (p.rank || i+1); });
        if (myrankEl) {
            myrankEl.style.cssText = 'position:absolute;bottom:20px;right:20px;opacity:0;transition:opacity .7s;z-index:5;';
            myrankEl.innerHTML = '<div class="acdc-qz-vous-card">'
                + '<div class="acdc-qz-vous-label">VOUS</div>'
                + (myRank ? '<div class="acdc-qz-vous-rank">#'+myRank+'</div>' : '')
                + '<div class="acdc-qz-vous-score">'+myScore+' pts</div>'
                + '</div>';
        }

        setTimeout(function(){revealBlock(3);},1500);
        setTimeout(function(){revealBlock(2);},3500);
        setTimeout(function(){revealBlock(1); fireConfetti();},5500);
        setTimeout(function(){if(myrankEl) myrankEl.classList.add('is-visible');},7500);
    }

    function revealBlock(rank) {
        var b = document.querySelector('#acdc-qz-player-podium .acdc-qz-podium-block-'+rank);
        if (b) b.classList.add('is-visible');
    }

    function fireConfetti() {
        if (typeof window.confetti !== 'function') return;
        var canvas = document.getElementById('acdc-qz-player-confetti');
        if (canvas) {
            canvas.classList.add('is-active');
            var myConfetti = window.confetti.create(canvas,{resize:true,useWorker:true});
            [[0,0.5],[400,0.2],[800,0.8]].forEach(function(pair){
                setTimeout(function(){myConfetti({particleCount:80,spread:70,origin:{x:pair[1],y:0.6},colors:['#d6a353','#0f2c52','#fbf2e3','#fef6e4']});},pair[0]);
            });
        }
    }

    function esc(s) {
        return String(s||'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});
    }

})();
