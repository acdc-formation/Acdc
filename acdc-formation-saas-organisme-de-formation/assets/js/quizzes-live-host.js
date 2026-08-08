/**
 * ACDC Formation SAAS — Quiz Live Host JS (3.21.04.2-b)
 * Conforme au mockup Visuel_du_quiz_live.pdf
 */
(function () {
    'use strict';

    var root = document.querySelector('.acdc-qz-live-host');
    if (!root) { return; }

    var sessionId = parseInt(root.dataset.sessionId, 10);
    var nonce     = root.dataset.nonce;
    var ajaxUrl   = root.dataset.ajaxUrl;
    var playerUrl = root.dataset.playerUrl;

    var POLL_INTERVAL = 1500;
    var state = { current: 'lobby', lastQuestionId: 0, hasShownReveal: false, podiumAnimated: false, allAnswered: false };

    /* ACDC 3.25.168 — Le formateur ne doit pas pouvoir révéler une question encore
       ouverte. Trois cas rendent la main :
         — la question n'a pas de chronomètre (réponse libre, par exemple) : sans cette
           porte, l'écran serait bloqué à jamais ;
         — le chronomètre est arrivé à zéro ;
         — tout le monde a répondu : plus personne à attendre. */
    function canRevealNow() {
        if ( ! hostTimerData.limit || hostTimerData.limit <= 0 ) { return true; }
        if ( ! hostTimerData.active ) { return true; }
        if ( state.allAnswered ) { return true; }
        return ( Date.now() - hostTimerData.startedAt ) >= hostTimerData.limit;
    }

    /* Reflète ce verrou dans le bouton : grisé et explicite plutôt que muet au clic. */
    function refreshRevealBtn() {
        var btn = document.getElementById('acdc-qz-host-reveal-btn');
        if ( ! btn ) { return; }
        var ok = canRevealNow();
        btn.disabled = ! ok;
        btn.style.opacity = ok ? '' : '.45';
        btn.style.cursor  = ok ? '' : 'not-allowed';
        btn.title = ok
            ? 'Afficher la bonne réponse'
            : 'Disponible à la fin du temps imparti, ou dès que tout le monde a répondu';
    }

    // ====== QR CODE =======================================================
    try {
        if (typeof window.qrcode === 'function') {
            var qr = window.qrcode(0, 'M');
            qr.addData(playerUrl);
            qr.make();
            var qrEl = document.getElementById('acdc-qz-host-qr');
            if (qrEl) {
                qrEl.innerHTML = qr.createSvgTag(4, 0);
                var svgEl = qrEl.querySelector('svg');
                if (svgEl) { svgEl.setAttribute('width','100%'); svgEl.setAttribute('height','100%'); svgEl.setAttribute('preserveAspectRatio','xMidYMid meet'); }
            }
        }
    } catch (e) {}

    // ====== SONS ==========================================================
    function playSound(type) {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            if (type === 'podium') {
                [523,659,784,1046].forEach(function(f,i){
                    var o=ctx.createOscillator(),g=ctx.createGain();
                    o.connect(g);g.connect(ctx.destination);o.frequency.value=f;o.type='sine';
                    var t=ctx.currentTime+i*0.18;
                    g.gain.setValueAtTime(0.25,t);g.gain.exponentialRampToValueAtTime(0.001,t+0.45);
                    o.start(t);o.stop(t+0.45);
                });
            } else if (type === 'reveal') {
                // Accord de ré majeur — annonce la révélation
                [587,740,880].forEach(function(f,i){
                    var o=ctx.createOscillator(),g=ctx.createGain();
                    o.connect(g);g.connect(ctx.destination);o.frequency.value=f;o.type='sine';
                    var t=ctx.currentTime+i*0.04;
                    g.gain.setValueAtTime(0.22,t);g.gain.exponentialRampToValueAtTime(0.001,t+0.5);
                    o.start(t);o.stop(t+0.5);
                });
            } else if (type === 'question_start') {
                var o=ctx.createOscillator(),g=ctx.createGain();
                o.connect(g);g.connect(ctx.destination);
                o.frequency.setValueAtTime(440,ctx.currentTime);
                o.frequency.exponentialRampToValueAtTime(880,ctx.currentTime+0.12);
                o.type='sine';
                g.gain.setValueAtTime(0.18,ctx.currentTime);g.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+0.18);
                o.start(ctx.currentTime);o.stop(ctx.currentTime+0.18);
            } else if (type === 'tick') {
                var o=ctx.createOscillator(),g=ctx.createGain();
                o.connect(g);g.connect(ctx.destination);
                o.frequency.value=1200;o.type='square';
                g.gain.setValueAtTime(0.08,ctx.currentTime);g.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+0.07);
                o.start(ctx.currentTime);o.stop(ctx.currentTime+0.07);
            } else if (type === 'time_up') {
                var o=ctx.createOscillator(),g=ctx.createGain();
                o.connect(g);g.connect(ctx.destination);
                o.frequency.setValueAtTime(600,ctx.currentTime);o.frequency.exponentialRampToValueAtTime(200,ctx.currentTime+0.4);
                o.type='sawtooth';
                g.gain.setValueAtTime(0.2,ctx.currentTime);g.gain.exponentialRampToValueAtTime(0.001,ctx.currentTime+0.45);
                o.start(ctx.currentTime);o.stop(ctx.currentTime+0.45);
            }
        } catch(e){}
    }

    // ====== AJAX ==========================================================
    function ajax(action, params, cb) {
        var body = new URLSearchParams();
        body.append('action', action);
        body.append('_wpnonce', nonce);
        body.append('session_id', sessionId);
        Object.keys(params||{}).forEach(function(k){body.append(k,params[k]);});
        fetch(ajaxUrl,{method:'POST',body:body,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){cb&&cb(j);}).catch(function(err){console.error(action,err);cb&&cb({success:false,data:{message:err.message}});});
    }

    // ====== STATE MACHINE =================================================
    function showState(name) {
        if (state.current === name) { return; }
        state.current = name;
        root.querySelectorAll('.acdc-qz-host-state').forEach(function(el){
            el.classList.toggle('is-active', el.dataset.state === name);
        });
    }

    function poll() {
        ajax('acdc_of_qz_host_lobby_state', {}, function(j) {
            if (!j.success) { return; }
            updateFromState(j.data);
        });
    }

    function updateFromState(data) {
        // Compteurs
        var count = (data.participants||[]).filter(function(p){return p.status!=='cancelled';}).length;
        var el = document.getElementById('acdc-qz-host-count-pill');
        if (el) el.textContent = count;
        var elF = document.getElementById('acdc-qz-host-count-footer');
        if (elF) elF.textContent = count;

        renderParticipants(data.participants||[]);
        state.allAnswered = !! data.all_answered;
        refreshRevealBtn();

        if (data.status === 'lobby') {
            stopHostTimer();
            showState('lobby');
        } else if (data.status === 'in_progress') {
            // Masquer bouton Commencer
            var sb = document.getElementById('acdc-qz-host-start-btn');
            if (sb) sb.style.display = 'none';

            var qid = data.current_q_id || 0;
            if (qid !== state.lastQuestionId) {
                // Nouvelle question détectée — TOUJOURS afficher la question en premier
                state.lastQuestionId = qid;
                state.hasShownReveal = false;
                var tl = (data.current_question && data.current_question.time_limit) ? parseInt(data.current_question.time_limit, 10) : 0;
                var elapsed = data.current_q_elapsed_seconds || 0;

                if (tl > 0 && elapsed >= tl) {
                    // Temps déjà écoulé côté serveur → afficher le reveal directement
                    state.hasShownReveal = true;
                    renderReveal(data);
                    showState('reveal');
                } else {
                    // Afficher la question + démarrer le timer
                    // Le reveal se fera via timer ou bouton "Réponse" — jamais via all_answered
                    startHostTimer(elapsed, tl);
                    renderQuestion(data);
                    showState('question');
                }
            }
            /* ACDC 3.25.159 — Tant que la question reste ouverte, la révélation SUIT
               le serveur. Auparavant, une fois la révélation affichée, la boucle de
               scrutation ne redessinait plus rien : le décompte restait figé sur les
               valeurs du seul appel qui l'avait déclenchée. Or l'horloge de l'écran
               formateur et celle de l'apprenant sont indépendantes — si celle du
               formateur arrive à zéro en avance, la révélation est calculée avant
               que la réponse ne soit enregistrée, et plus rien ne la corrige ensuite.
               La recette a mesuré ce figement à T+5s, T+10s et T+30s. On redessine
               donc à chaque scrutation, tant que la question n'est pas close et que
               l'on est bien sur la même. Le rendu est idempotent : réécrire les mêmes
               valeurs ne coûte rien. */
            else if ( state.hasShownReveal && data.current_question
                      && parseInt( data.current_q_id, 10 ) === parseInt( state.lastQuestionId, 10 ) ) {
                /* ACDC 3.25.160 — Mise à jour NON DESTRUCTIVE. Rappeler renderReveal()
                   à chaque scrutation reconstruisait tout le bloc de réponses et
                   relançait ses animations d'entrée, qui partent d'une opacité nulle :
                   à 1,5 s d'intervalle, les tuiles pouvaient ne jamais devenir
                   visibles. On se contente donc de rafraîchir les nombres déjà
                   affichés, sans toucher au balisage. */
                updateRevealCounts( data.current_question );
            }
        } else if (data.status === 'ended') {
            stopHostTimer();
            if (!state.podiumAnimated) {
                state.podiumAnimated = true;
                fetchAndAnimatePodium();
            }
            showState('podium');
        }
    }

    // ====== TAB-SWITCH (HOST — réception et affichage ⚠️) ================
    var tabSwitchIds = {};

    function renderParticipants(list) {
        var ul = document.getElementById('acdc-qz-host-participants-list');
        if (!ul) return;
        var active = list.filter(function(p){return p.status!=='cancelled';});
        if (!active.length) { ul.innerHTML = '<li class="acdc-qz-host-participants-empty">En attente de participants</li>'; return; }
        var html = '';
        active.forEach(function(p){
            if (p.tab_switch > 0) tabSwitchIds[p.id] = p.tab_switch;
            var warn = tabSwitchIds[p.id] ? ' has-tab-switch' : '';
            var warnBadge = tabSwitchIds[p.id] ? ' <span class="acdc-qz-tab-switch-badge" title="Changements d\'onglet détectés">⚠️ ' + tabSwitchIds[p.id] + '</span>' : '';
            html += '<li class="acdc-qz-host-participant' + warn + '" data-pid="' + p.id + '"><span>' + (p.avatar==='homme'?'👨':'👩') + '</span><span>' + esc(p.nickname) + '</span>' + warnBadge + '</li>';
        });
        ul.innerHTML = html;
    }

    // ====== TIMER HOST ====================================================
    var hostTimerRaf = null;
    var hostTimerData = { startedAt: 0, limit: 0, active: false };

    function startHostTimer(elapsedSec, limitSec) {
        stopHostTimer();
        var wrap = document.getElementById('acdc-qz-host-timer-wrap');
        if (!limitSec || limitSec <= 0) {
            if (wrap) wrap.style.display = 'none';
            hostTimerData.limit = 0;
            hostTimerData.active = false;
            refreshRevealBtn();
            return;
        }
        if (wrap) wrap.style.display = 'block';
        // On recalcule startedAt à partir des secondes déjà écoulées (fourni par le serveur, pas de parsing de date)
        hostTimerData.startedAt = Date.now() - (elapsedSec * 1000);
        hostTimerData.limit  = limitSec * 1000;
        hostTimerData.active = true;
        playSound('question_start');
        tickHostTimer();
    }

    function stopHostTimer() {
        hostTimerData.active = false;
        if (hostTimerRaf) { cancelAnimationFrame(hostTimerRaf); hostTimerRaf = null; }
        refreshRevealBtn();
    }

    function tickHostTimer() {
        if (!hostTimerData.active) return;
        var elapsed   = Date.now() - hostTimerData.startedAt;
        var remaining = Math.max(0, hostTimerData.limit - elapsed);
        var pct       = remaining / hostTimerData.limit * 100;
        var secLeft   = Math.ceil(remaining / 1000);
        var bar   = document.getElementById('acdc-qz-host-timer-bar');
        var count = document.getElementById('acdc-qz-host-timer-count');
        if (bar)   { bar.style.width = pct + '%'; bar.classList.toggle('is-urgent', pct <= 25); }
        if (count) { count.textContent = secLeft + 's'; count.classList.toggle('is-urgent', pct <= 25); }
        refreshRevealBtn();
        // Ticks sonores sur les 3 dernières secondes (seulement quand le chiffre change)
        if (remaining > 0 && secLeft <= 3 && secLeft !== (hostTimerData.lastTick || -1)) {
            hostTimerData.lastTick = secLeft;
            playSound('tick');
        }
        if (remaining > 0) {
            hostTimerRaf = requestAnimationFrame(tickHostTimer);
        } else {
            stopHostTimer();
            playSound('time_up');
            // Reveal automatique si pas encore révélé
            if (!state.hasShownReveal) {
                state.hasShownReveal = true;
                ajax('acdc_of_qz_host_lobby_state', {}, function(j) {
                    if (j.success) { renderReveal(j.data); showState('reveal'); playSound('reveal'); refreshRevealCounts(j.data.current_q_id); }
                });
            }
        }
    }


    /* ACDC 3.25.168 — Consigne « une seule » / « plusieurs » réponses, affichée sous
       l'énoncé. Elle ne vivait qu'en pied d'écran, en petit corps gris : sur une
       évaluation, ne pas savoir qu'on peut cocher plusieurs cases fausse le résultat. */
    function fillAnswerInstruction(el, type) {
        if (!el) { return; }
        var multi  = (type === 'qcm_multiple' || type === 'poll');
        var single = (type === 'qcm_single' || type === 'true_false');
        if (!multi && !single) { el.style.display = 'none'; return; }
        el.className = 'acdc-qz-answer-instruction ' + (multi ? 'is-multi' : 'is-single');
        el.textContent = multi
            ? (type === 'poll' ? '⚠ Plusieurs réponses possibles (sondage)' : '⚠ Plusieurs réponses possibles')
            : '● Une seule réponse possible';
        el.style.display = '';
    }

    function renderQuestion(data) {
        var q = data.current_question;
        if (!q) return;
        var letters = ['A','B','C','D','E','F'];
        document.getElementById('acdc-qz-host-q-title').textContent = q.title;
        fillAnswerInstruction(document.getElementById('acdc-qz-host-q-instruction'), q.type);
        document.getElementById('acdc-qz-host-q-num').textContent = (data.current_q_num||'?') + '/' + (data.total_q||'?');
        document.getElementById('acdc-qz-host-q-type').textContent = q.type_label||'';
        var html = '';
        (q.answers||[]).forEach(function(a,i){
            html += '<div class="acdc-qz-host-answer-tile acdc-qz-host-answer-tile-' + i + '"><span class="acdc-qz-host-answer-letter">' + (letters[i]||(i+1)) + '</span><span class="acdc-qz-host-answer-label">' + esc(a.label) + '</span></div>';
        });
        document.getElementById('acdc-qz-host-q-answers').innerHTML = html;
    }

    /* ACDC 3.25.157 — Le compte affiché au reveal était un INSTANTANÉ pris une seule
       fois, jamais rafraîchi. Le chronomètre de l'écran formateur et celui de
       l'apprenant ne sont pas la même horloge : une réponse partie dans les dernières
       secondes arrive après cet instantané, et l'écran formateur affichait alors
       « 0 bonne réponse sur 0 répondant » pendant que la base enregistrait bien la
       passation. On rejoue donc la lecture quelques instants après le reveal, et on
       ne redessine que si l'on est toujours sur la même question. */
    function refreshRevealCounts( questionId ) {
        /* ACDC 3.25.168 — Ces deux relectures appelaient renderReveal(), qui reconstruit
           tout le bloc et relance ses animations d'entrée : l'écran se retapait
           entièrement deux fois, à 1,5 s puis 4 s, sous les yeux du formateur et de la
           salle. La mise à jour non destructive existait déjà depuis la 3.25.160 — elle
           n'avait simplement jamais été branchée ici. Les nombres se corrigent
           maintenant en place, sans que rien ne bouge à l'écran. */
        [ 1500, 4000 ].forEach( function( delay ) {
            setTimeout( function() {
                if ( ! state.hasShownReveal ) { return; }
                ajax( 'acdc_of_qz_host_lobby_state', {}, function( j ) {
                    if ( ! j.success || ! j.data || ! j.data.current_question ) { return; }
                    if ( parseInt( j.data.current_q_id, 10 ) !== parseInt( questionId, 10 ) ) { return; }
                    if ( ! state.hasShownReveal ) { return; }
                    updateRevealCounts( j.data.current_question );
                } );
            }, delay );
        } );
    }

    /* ACDC 3.25.160 — Rafraîchit les compteurs d'une révélation DÉJÀ affichée.
       Ne reconstruit rien : si le balisage attendu n'est pas là, on ne fait rien
       plutôt que de redessiner et de casser l'affichage en place. */
    function updateRevealCounts( q ) {
        if ( ! q || ! q.reveal_answers || ! q.reveal_answers.length ) { return; }
        var container = document.getElementById('acdc-qz-host-reveal-answers');
        if ( ! container ) { return; }
        var tiles = container.querySelectorAll('.acdc-qz-host-reveal-tile');
        if ( tiles.length !== q.reveal_answers.length ) { return; }
        q.reveal_answers.forEach( function( a, i ) {
            var tile  = tiles[i];
            if ( ! tile ) { return; }
            var label = tile.querySelector('.acdc-qz-host-reveal-bar-label');
            var bar   = tile.querySelector('.acdc-qz-host-reveal-bar');
            if ( label ) { label.innerHTML = a.count + '&nbsp;(' + (a.percent||0) + '%)'; }
            if ( bar )   { bar.dataset.pct = (a.percent||0); bar.style.width = (a.percent||0) + '%'; }
        } );
        var statsEl = document.getElementById('acdc-qz-host-reveal-stats');
        if ( statsEl && q.count_total_parts !== undefined ) {
            var c = q.count_correct_parts || 0, t = q.count_total_parts || 0;
            if ( 'poll' !== ( q.type || '' ) && 'open_text' !== ( q.type || '' ) ) {
                statsEl.textContent = c + ' bonne' + (c > 1 ? 's' : '') + ' réponse' + (c > 1 ? 's' : '') + ' sur ' + t + ' répondant' + (t > 1 ? 's' : '');
            }
        }
    }

    function renderReveal(data) {
        var q = data.current_question;
        if (!q) return;
        var letters = ['A','B','C','D','E','F'];
        var revealAnswers = (q.reveal_answers && q.reveal_answers.length)
            ? q.reveal_answers
            : (q.answers||[]).map(function(a){ return {id:a.id, label:a.label, is_correct:0, count:0, percent:0}; });
        var hasStats = !!(q.reveal_answers && q.reveal_answers.length);
        var type = q.type || '';

        document.getElementById('acdc-qz-host-reveal-title').textContent = q.title;
        document.getElementById('acdc-qz-host-reveal-q-num').textContent = (data.current_q_num||'?') + '/' + (data.total_q||'?');
        document.getElementById('acdc-qz-host-reveal-q-type').textContent = q.type_label||'';

        var html = '';
        var container = document.getElementById('acdc-qz-host-reveal-answers');

        // ---- MODE RÉPONSE LIBRE (open_text) : affiche la réponse attendue ----
        if (type === 'open_text') {
            container.className = 'acdc-qz-host-open-text-reveal';
            var expected = q.expected_answer || '';
            if (expected) {
                html += '<div class="acdc-qz-host-open-text-expected">';
                html += '<div class="acdc-qz-host-open-text-expected-label">Réponse attendue :</div>';
                html += '<div class="acdc-qz-host-open-text-expected-body">' + esc(expected) + '</div>';
                html += '</div>';
            }
            html += '<p class="acdc-qz-host-open-text-note">Les r\u00e9ponses des apprenants seront corrig\u00e9es manuellement depuis l\u2019espace r\u00e9sultats.</p>';
            container.innerHTML = html;

        // ---- MODE SONDAGE (poll) : barres horizontales, pas de correct/incorrect ----
        } else if (type === 'poll') {
            container.className = 'acdc-qz-host-poll-reveal';
            revealAnswers.forEach(function(a,i){
                var pct = a.percent || 0;
                var colors = ['#feeceb','#e9f2fa','#fff4d6','#e8f4ec','#f3e5f5'];
                html += '<div class="acdc-qz-poll-bar-row" style="border-left:4px solid; border-color:'+(['#ef9a9a','#90caf9','#ffe082','#a5d6a7','#ce93d8'][i%5]||'#d6a353')+'">';
                html += '<div class="acdc-qz-poll-bar-label"><span class="acdc-qz-host-answer-letter" style="background:'+colors[i%5]+'">'+letters[i]+'</span><span>'+esc(a.label)+'</span></div>';
                html += '<div class="acdc-qz-poll-bar-track"><div class="acdc-qz-poll-bar-fill" data-pct="'+pct+'" style="width:0%;background:'+(colors[i%5]||'#e9f2fa')+'"></div></div>';
                html += '<div class="acdc-qz-poll-bar-count">'+a.count+' <span>('+pct+'%)</span></div>';
                html += '</div>';
            });
            container.innerHTML = html;
            requestAnimationFrame(function(){ requestAnimationFrame(function(){
                container.querySelectorAll('.acdc-qz-poll-bar-fill').forEach(function(b){ b.style.width=(b.dataset.pct||'0')+'%'; });
            }); });

        // ---- MODE PUZZLE : 2 colonnes — slots numérotés à gauche, réponses à droite ----
        } else if (type === 'puzzle') {
            container.className = 'acdc-qz-host-puzzle-reveal';
            var colors = ['#feeceb','#e9f2fa','#fff4d6','#e8f4ec','#f3e5f5'];
            var slotsHtml = '';
            var tilesHtml = '';
            revealAnswers.forEach(function(a,i){
                // Slot gauche (numéroté)
                slotsHtml += '<div class="acdc-qz-host-puzzle-slot" style="background:'+colors[i%5]+'">'
                    + '<span class="acdc-qz-host-puzzle-num" style="background:#d6a353;color:#fff">'+(i+1)+'</span>'
                    + '<span class="acdc-qz-host-puzzle-label">'+esc(a.label)+'</span>'
                    + '</div>';
                // Tile droite (ordre original mélangé — vide au reveal = ordre correct déjà à gauche)
            });
            html = '<div class="acdc-qz-host-puzzle-slots-col">'+slotsHtml+'</div>'
                 + '<div class="acdc-qz-host-puzzle-bank-col"></div>';
            container.innerHTML = html;

        // ---- MODE QCM / Vrai-Faux : correct en vert, incorrect en rouge ----
        } else {
            container.className = 'acdc-qz-host-reveal-answers-grid';
            revealAnswers.forEach(function(a,i){
                var isCorrect = !!a.is_correct;
                var colorClass = hasStats ? (isCorrect ? ' is-reveal-correct' : ' is-reveal-wrong') : (' acdc-qz-host-answer-tile-'+i);
                html += '<div class="acdc-qz-host-reveal-tile'+colorClass+'">';
                html += '<div class="acdc-qz-host-reveal-tile-top"><span class="acdc-qz-host-answer-letter">'+(letters[i]||(i+1))+'</span><span class="acdc-qz-host-answer-label">'+esc(a.label)+'</span>';
                if (hasStats && isCorrect) html += '<span class="acdc-qz-host-reveal-badge">✓</span>';
                html += '</div>';
                if (hasStats) {
                    html += '<div class="acdc-qz-host-reveal-bar-wrap"><div class="acdc-qz-host-reveal-bar" data-pct="'+(a.percent||0)+'" style="width:0%"></div><span class="acdc-qz-host-reveal-bar-label">'+a.count+'&nbsp;('+(a.percent||0)+'%)</span></div>';
                }
                html += '</div>';
            });
            container.innerHTML = html;
            if (hasStats) {
                requestAnimationFrame(function(){ requestAnimationFrame(function(){
                    container.querySelectorAll('.acdc-qz-host-reveal-bar').forEach(function(bar){
                        bar.style.transition = 'width 0.7s cubic-bezier(0.4,0,0.2,1)';
                        bar.style.width = (bar.dataset.pct||'0') + '%';
                    });
                }); });
            }
            // Animation d'entrée des tuiles en cascade
            container.querySelectorAll('.acdc-qz-host-reveal-tile').forEach(function(tile, i){
                tile.style.opacity = '0';
                tile.style.transform = 'translateY(18px)';
                tile.style.transition = 'opacity 0.35s ease ' + (i*0.08) + 's, transform 0.35s ease ' + (i*0.08) + 's';
                requestAnimationFrame(function(){ requestAnimationFrame(function(){
                    tile.style.opacity = '1';
                    tile.style.transform = 'translateY(0)';
                }); });
            });
            // Compteur — utilise les données serveur (participants distincts, pas lignes)
            var countCorrect = q.count_correct_parts !== undefined ? q.count_correct_parts : 0;
            var countTotal   = q.count_total_parts   !== undefined ? q.count_total_parts   : 0;
            var statsEl = document.getElementById('acdc-qz-host-reveal-stats');
            if (statsEl) {
                if (type === 'poll') {
                    statsEl.textContent = 'Résultat du sondage — ' + countTotal + ' apprenant' + (countTotal > 1 ? 's' : '') + ' ont répondu';
                } else if (type === 'open_text') {
                    statsEl.textContent = 'Réponses corrigées par le formateur — ' + countTotal + ' apprenant' + (countTotal > 1 ? 's' : '') + ' ont répondu';
                } else {
                    /* ACDC 3.25.157 — countTotal compte les RÉPONDANTS, pas les
                       participants connectés : « sur 0 apprenant » s'affichait alors
                       qu'un participant était bien présent. On nomme ce qui est compté. */
                    statsEl.textContent = countCorrect + ' bonne' + (countCorrect > 1 ? 's' : '') + ' réponse' + (countCorrect > 1 ? 's' : '') + ' sur ' + countTotal + ' répondant' + (countTotal > 1 ? 's' : '');
                }
                statsEl.style.display = 'block';
            }
        }
    }

    // ====== PODIUM ========================================================
    function fetchAndAnimatePodium() {
        ajax('acdc_of_qz_host_lobby_state', {}, function(j) {
            if (!j.success) return;
            var list = (j.data.participants||[]).filter(function(p){return p.status==='completed';});
            list.sort(function(a,b){return (b.score||0)-(a.score||0);});
            // Force le layout du podium en JS (contourne les conflits CSS)
            var stateEl = document.querySelector('.acdc-qz-host-state-podium');
            if (stateEl) {
                stateEl.style.cssText = 'display:flex!important;flex-direction:column!important;position:fixed!important;top:0!important;left:0!important;right:0!important;bottom:0!important;width:100vw!important;height:100vh!important;box-sizing:border-box!important;padding-top:65.1vh!important;z-index:50!important;background:#fef8f2!important;';
                var wrap = stateEl.querySelector('.acdc-qz-podium-wrap');
                if (wrap) {
                    wrap.style.cssText = 'position:relative!important;flex:1!important;min-height:0!important;width:800px!important;max-width:100%!important;margin:0 auto!important;transform:none!important;top:auto!important;bottom:auto!important;left:auto!important;right:auto!important;';
                }
            }
            renderPodiumAnimated(list);
        });
    }

    function renderPodiumAnimated(sorted) {
        var container = document.getElementById('acdc-qz-host-podium');
        var restEl = document.getElementById('acdc-qz-host-leaderboard-rest');
        if (!container) return;

        var top3 = sorted.slice(0,3);
        var assets = window.acdcQzPodiumAssets || {};

        // Positions exactes par avatar (calées sur le fichier Podium.png)
        // Positions par breakpoint — ajustées pixel par pixel via retours visuels
        // R1/R2/R3 : l = centre du personnage en % du wrap (800px), translateX(-50%) appliqué
        // 1px = 0.125% sur un wrap de 800px
        var w = window.innerWidth;
        var PODIUM_POS;
        if (w > 1366) {
            // Écran large ≥2400px — calibré via DevTools (sans translateX)
            PODIUM_POS = {
                homme: {
                    1: { b:'84%', l:'35%',   t:'none', r:null, ml:'0', mh:'55vh' },
                    2: { b:'56%', l:'0%',    t:'none', r:null, ml:'0', mh:'55vh' },
                    3: { b:'50%', l:'68.2%', t:'none', r:null, ml:'0', mh:'52.5vh' },
                },
                femme: {
                    1: { b:'84%', l:'35%',   t:'none', r:null, ml:'0', mh:'55vh' },
                    2: { b:'56%', l:'0%',    t:'none', r:null, ml:'0', mh:'55vh' },
                    3: { b:'50%', l:'68.2%', t:'none', r:null, ml:'0', mh:'52.5vh' },
                },
            };
        } else {
            // Portable ≤1366px : R1 -25px, R2 +20px, R3 +5px
            PODIUM_POS = {
                homme: {
                    1: { b:'27.8%', l:'47.3%', t:'translateX(-50%)', r:null, ml:'0', mh:'55vh' },
                    2: { b:'18.0%', l:'33.7%', t:'translateX(-50%)', r:null, ml:'0', mh:'55vh' },
                    3: { b:'15.7%', l:'69.4%', t:'translateX(-50%)', r:null, ml:'0', mh:'52.5vh' },
                },
                femme: {
                    1: { b:'27.8%', l:'47.3%', t:'translateX(-50%)', r:null, ml:'0', mh:'55vh' },
                    2: { b:'18.0%', l:'33.7%', t:'translateX(-50%)', r:null, ml:'0', mh:'55vh' },
                    3: { b:'15.7%', l:'69.4%', t:'translateX(-50%)', r:null, ml:'0', mh:'52.5vh' },
                },
            };
        }

        // Structure : image podium en fond + blocs personnages
        container.innerHTML = ''; // position CSS absolute top:0 bottom:0 left:0 right:0 conservée

        // Image podium base
        var podiumImg = document.createElement('img');
        podiumImg.src = assets['base'] || '';
        podiumImg.alt = 'Podium';
        podiumImg.className = 'acdc-qz-podium-base';
        container.appendChild(podiumImg);

        // 3 blocs personnages (ordre visuel : 2, 1, 3)
        [2,1,3].forEach(function(rank) {
            var p = top3[rank-1];
            var block = document.createElement('div');
            block.className = 'acdc-qz-podium-block acdc-qz-podium-block-' + rank;
            if (p) {
                var av = (p.avatar === 'homme') ? 'homme' : 'femme';
                var pos = PODIUM_POS[av][rank];
                var avatarUrl = assets[rank + '-' + av] || '';
                block.innerHTML = '<div class="acdc-qz-podium-name">' + esc(p.nickname) + '</div>'
                    + '<div class="acdc-qz-podium-score">' + Math.round(p.score) + ' pts</div>'
                    + '<img class="acdc-qz-podium-avatar" src="' + avatarUrl + '" alt="" />';
                // Positions inline — height-driven (vh)
                block.style.bottom     = pos.b;
                block.style.left       = pos.l  || 'auto';
                block.style.right      = pos.r  || 'auto';
                block.style.marginLeft = pos.ml || '0';
                block.style.transform  = pos.t  || 'none';
                block.style.width      = 'auto';
                // Hauteur avatar en vh
                var avatarImg = block.querySelector('.acdc-qz-podium-avatar');
                if (avatarImg && pos.mh) {
                    avatarImg.style.maxHeight = pos.mh;
                    avatarImg.style.width     = 'auto';
                    avatarImg.style.height    = 'auto';
                }
            }
            container.appendChild(block);
        });

        // Tableau classement reste (host: leaderboard complet)
        if (restEl && sorted.length > 0) {
            var tableHtml = '<table class="acdc-qz-leaderboard-table"><thead><tr><th>Position</th><th>Nom</th><th>Score</th></tr></thead><tbody>';
            sorted.forEach(function(p,i){
                tableHtml += '<tr><td><strong>' + (i+1) + '</strong></td><td>' + esc(p.nickname) + '</td><td>' + Math.round(p.score) + '</td></tr>';
            });
            tableHtml += '</tbody></table>';
            restEl.innerHTML = tableHtml;
        }

        // Animations séquentielles
        setTimeout(function(){revealBlock(3);}, 1500);
        setTimeout(function(){revealBlock(2);}, 3500);
        setTimeout(function(){revealBlock(1); fireConfetti(); playSound('podium');}, 5500);
        setTimeout(function(){ if(restEl) restEl.classList.add('is-visible'); }, 8000);
    }

    function revealBlock(rank) {
        var b = document.querySelector('.acdc-qz-podium-block-'+rank);
        if (b) b.classList.add('is-visible');
    }

    function fireConfetti() {
        if (typeof window.confetti !== 'function') return;
        var canvas = document.getElementById('acdc-qz-host-confetti');
        if (canvas) {
            canvas.classList.add('is-active');
            var myConfetti = window.confetti.create(canvas,{resize:true,useWorker:true});
            [[0,0.5],[400,0.2],[800,0.8],[1500,0.5]].forEach(function(pair){
                setTimeout(function(){ myConfetti({particleCount:100,spread:70,origin:{x:pair[1],y:0.6},colors:['#d6a353','#0f2c52','#fbf2e3','#fef6e4']}); }, pair[0]);
            });
        }
    }

    // ====== BOUTONS =======================================================
    var startBtn = document.getElementById('acdc-qz-host-start-btn');
    if (startBtn) startBtn.addEventListener('click', function(){
        ajax('acdc_of_qz_host_start_quiz',{},function(j){ if(!j.success) alert(j.data&&j.data.message||'Erreur'); });
    });

    var revealBtn = document.getElementById('acdc-qz-host-reveal-btn');
    if (revealBtn) revealBtn.addEventListener('click', function(){
        if (state.hasShownReveal) return; // Déjà révélé — ignorer
        /* ACDC 3.25.168 — On ne révèle pas une question encore ouverte. Le bouton
           était actif dès l'affichage : un clic de trop et la salle voyait la bonne
           réponse pendant que les apprenants répondaient encore. Le verrou saute
           quand le chronomètre est à zéro, ou avant s'il ne reste plus personne à
           attendre — inutile de faire patienter tout le monde. */
        if ( ! canRevealNow() ) { return; }
        state.hasShownReveal = true;
        stopHostTimer();
        ajax('acdc_of_qz_host_lobby_state',{},function(j){
            if (j.success && j.data.current_question) { renderReveal(j.data); showState('reveal'); playSound('reveal'); refreshRevealCounts(j.data.current_q_id); }
        });
    });

    var nextBtn = document.getElementById('acdc-qz-host-next-btn');
    if (nextBtn) nextBtn.addEventListener('click', function(){
        ajax('acdc_of_qz_host_next_question',{},function(j){
            if (!j.success) { alert(j.data&&j.data.message||'Erreur'); return; }
            state.hasShownReveal = false;
            state.lastQuestionId = 0; // Force la détection de la nouvelle question au prochain poll
            poll(); // Poll immédiat sans attendre le setInterval
        });
    });

    var fsBtn = document.getElementById('acdc-qz-host-fullscreen-btn');
    if (fsBtn) fsBtn.addEventListener('click', function(){
        if (document.fullscreenElement) document.exitFullscreen();
        else document.documentElement.requestFullscreen();
    });

    function esc(s) {
        return String(s||'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});
    }

    poll();
    setInterval(poll, POLL_INTERVAL);
})();
