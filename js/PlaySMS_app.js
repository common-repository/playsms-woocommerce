jQuery(document).ready(function () {

    jQuery(document).on('submit', '#playSMS-addNewEventForm', function (event) {
        event.preventDefault();
        var eventName = jQuery('#playSMS-eventName').val();
        var eventMessage = jQuery('#playSMS-eventMessage').val();
        var eventActionType = jQuery('#playSMS-eventActionType').find(":selected").text();
        var associatedHook = jQuery('#playSMS-eventActionType').find(":selected").val();
        var eventHeader = jQuery('#playSMS-apiHeader').val();
        var convertCharacters = 1;
        if (jQuery('#playSMS-changePolishLettersCheckbox').is(":checked")) {
            convertCharacters = 1;
        } else {
            convertCharacters = 0;
        }

        jQuery.ajax({
            action: 'playSMS_addNewEventAjax',
            type: "POST",
            url: psAjaxObject.ajax_url,
            data: {
                action: 'playSMS_addNewEventAjax',
                eventName: eventName,
                eventMessage: eventMessage,
                eventActionType: eventActionType,
                convertCharacters: convertCharacters,
                eventHeader: eventHeader,
                associatedHook: associatedHook

            },
            success: function (response) {
                if (response == 1) {
                    alert("Event został dodany prawidłowo.")
                    jQuery("#playSMS-addNewEventForm")[0].reset();
                } else {
                    alert(response)
                }
                loadAllEvents();

            }
        });

    });


    /**
     * Save caret position on loosing focus and add variable in message at the saved caret position
     */
    var caretStart, caretEnd;

    jQuery('#playSMS-eventMessage').on('blur', function (e) {
        caretStart = this.selectionStart;
        caretEnd = this.selectionEnd;
    });

    jQuery('.playSMS-addVariableToMessage').on('click', function () {
        jQuery('#playSMS-eventMessage').focus();
        var textToAdd = "{{" + jQuery(this).val() + "}} ";
        var eventsTextareaMessage = jQuery('#playSMS-eventMessage');

        // set text area value to: text before caret + desired text + text after caret
        eventsTextareaMessage.val(eventsTextareaMessage.val().substring(0, caretStart)
            + textToAdd
            + eventsTextareaMessage.val().substring(caretEnd));

        // put caret at right position again
        eventsTextareaMessage.selectionStart = eventsTextareaMessage.selectionEnd = caretStart + textToAdd.length;
        jQuery('#playSMS-eventMessage').keyup();
    });

    jQuery(document).on("click", ".row-actions a", function (e) {
        e.preventDefault();
        var clickedActionElementID = jQuery(this).attr("id");
        var eventIDSplit = clickedActionElementID.split("-");
        var eventID = eventIDSplit[2];
        var action = eventIDSplit[1];

        if (action === 'editEvent') {
            jQuery.ajax({
                action: 'playSMS_editEventAjax',
                type: 'POST',
                url: psAjaxObject.ajax_url,
                data: {
                    action: 'playSMS_editEventAjax',
                    editedEventID: eventID
                },
                success: function (response) {
                    jQuery("#playSMS-singleEvent-" + eventID).replaceWith(response);
                    var textAreaCount = jQuery('#playSMS-eventMessage-' + eventID).val().length;
                    var messageWritten = jQuery('#playSMS-eventMessage-' + eventID).val();
                    var $remaining = jQuery('#playSMS-count_message_edit-' + eventID),
                        $messages = $remaining.next();
                
                    if (jQuery('#playSMS-changePolishLettersCheckbox-' + eventID).is(':checked')) {
                        var textAreaValue = jQuery('#playSMS-eventMessage-' + eventID).val();
                        changeSpecialCharactersToNormalCharacters(textAreaValue, eventID);
                        text_max = 160;
                    } else {
                        text_max = 70;
                    }
                    
                    var chars = textAreaCount,
                        messages = Math.ceil(textAreaCount / text_max),
                        remaining = messages * text_max - (textAreaCount % (messages * text_max) || messages * text_max);
                    if (messages > 5) {
                        $remaining.html("<p>Wykorzystano limit znaków.</p>");
                    } else {
                        $remaining.html("<p>Liczba wiadomości SMS: " + messages + "<br>Zostało " + remaining + " znaków</p>");
                    }

                    if (chars === 0 && (messages === 0 || messages ===1)) {
                        if (jQuery('#playSMS-changePolishLettersCheckbox-' + eventID).prop('checked', false)) {
                            $remaining.html("<p>Liczba wiadomości SMS: 1<br>Zostało 70 znaków</p>");
                        } else {
                            $remaining.html("<p>Liczba wiadomości SMS: 1<br>Zostało 160 znaków</p>");
                        }
                    }

                    jQuery('#playSMS-changePolishLettersCheckbox-' + eventID).change(function () {
                        var textAreaCount = jQuery('#playSMS-eventMessage-' + eventID).val().length;
                        var textAreaValue = jQuery('#playSMS-eventMessage-' + eventID).val();
                        var $remaining = jQuery('#playSMS-count_message_edit-' + eventID),
                            $messages = $remaining.next();
                        if (jQuery(this).is(':checked')) {
                            changeSpecialCharactersToNormalCharacters(textAreaValue, eventID);
                            text_max = 160;
                        } else {
                            text_max = 70;
                        }
                        var chars = this.value.length,
                            messages = Math.ceil(textAreaCount / text_max),
                            remaining = messages * text_max - (textAreaCount % (messages * text_max) || messages * text_max);
                        if (messages > 5) {
                            $remaining.html("<p>Wykorzystano limit znaków.</p>");
                        } else {
                            $remaining.html("<p>Liczba wiadomości SMS: " + messages + "<br>Zostało " + remaining + " znaków</p>");
                        }
                        if (chars === 0 && messages === 1 || messages === 0) {
                            if (jQuery('#playSMS-changePolishLettersCheckbox-' + eventID).is(':checked')) {
                                $remaining.html("<p>Liczba wiadomości SMS: 1<br>Zostało 160 znaków</p>");
                            } else {
                                $remaining.html("<p>Liczba wiadomości SMS: 1<br>Zostało 70 znaków</p>");
                            }
                        }
                    });
                    jQuery('#playSMS-eventMessage-' + eventID).keyup(function () {
                        var $remaining = jQuery('#playSMS-count_message_edit-' + eventID),
                            $messages = $remaining.next();
                        var chars = this.value.length,
                            messages = Math.ceil(chars / text_max),
                            remaining = messages * text_max - (chars % (messages * text_max) || messages * text_max);
                        if(jQuery('#playSMS-changePolishLettersCheckbox-' + eventID).is(':checked')) {
                            var textAreaValue = jQuery('#playSMS-eventMessage-' + eventID).val();
                            changeSpecialCharactersToNormalCharacters(textAreaValue, eventID);
                        }
                        if (messages > 5) {
                            $remaining.html("<p>Wykorzystano limit znaków.</p>");
                        } else {
                            $remaining.html("<p>Liczba wiadomości SMS: " + messages + "<br>Zostało " + remaining + " znaków</p>");
                        }
                        if (chars === 0 && (messages === 0 || messages ===1)) {
                            if (jQuery('#playSMS-changePolishLettersCheckbox-' + eventID).is(':checked')) {
                                $remaining.html("<p>Liczba wiadomości SMS: 1<br>Zostało 160 znaków</p>");
                            } else {
                                $remaining.html("<p>Liczba wiadomości SMS: 1<br>Zostało 70 znaków</p>");
                            }
                        }
                    });
                }
            });
        } else if (action === 'removeEvent') {
            jQuery.ajax({
                action: 'playSMS_removeEventAjax',
                type: 'POST',
                url: psAjaxObject.ajax_url,
                data: {
                    action: 'playSMS_removeEventAjax',
                    eventID: eventID
                },
                success: function (response) {
                    var responseint = parseInt(response);
                    if (responseint === 1) {
                        alert("Pomyślnie usunięto event.");
                    } else if (responseint === 0) {
                        alert("Wystąpił błąd podczas usuwania eventu.")
                    }
                    loadAllEvents();
                }
            });
        } else if (action === 'saveEvent') {

            var eventName = jQuery('#playSMS-eventName-' + eventID).val();
            var eventMessage = jQuery('#playSMS-eventMessage-' + eventID).val();
            var eventActionType = jQuery('#playSMS-eventActionType-' + eventID).find(":selected").text();
            var associatedHook = jQuery('#playSMS-eventActionType-' + eventID).find(":selected").val();
            var eventHeader = jQuery('#playSMS-eventHeader-' + eventID).find(":selected").text();
            var convertCharacters = '';
            if (jQuery('#playSMS-changePolishLettersCheckbox-' + eventID).is(':checked')) {
                convertCharacters = 1;
            } else {
                convertCharacters = 0;
            }

            jQuery.ajax({
                action: 'playSMS_saveEventAjax',
                type: 'POST',
                url: psAjaxObject.ajax_url,
                data: {
                    action: 'playSMS_saveEventAjax',
                    eventID: eventID,
                    eventName: eventName,
                    eventMessage: eventMessage,
                    eventActionType: eventActionType,
                    convertCharacters: convertCharacters,
                    eventHeader: eventHeader,
                    associatedHook: associatedHook

                },
                success: function (response) {
                    var responseint = parseInt(response);
                    if (responseint === 1) {
                        alert("Edycja zakończona pomyślnie.");
                    } else if (responseint === 0) {
                        alert("Wystąpił błąd podczas edycji eventu.")
                    }
                    loadAllEvents();
                }
            });


        }
    });

    var text_max = 160;
    jQuery('#playSMS-countMessage').html("<p>Zostało " + text_max + " znaków</p>");


    jQuery('#playSMS-changePolishLettersCheckbox').change(function () {
        var textAreaCount = jQuery("#playSMS-eventMessage").val().length;
        var $remaining = jQuery('#playSMS-countMessage'),
            $messages = $remaining.next();
        if (jQuery(this).is(':checked')) {
            var textareaValue = jQuery("#playSMS-eventMessage").val();
            changeSpecialCharactersToNormalCharacters(textareaValue);
            text_max = 160;
        } else {
            text_max = 70;
        }

        var chars = this.value.length,
            messages = Math.ceil(textAreaCount / text_max),
            remaining = messages * text_max - (textAreaCount % (messages * text_max) || messages * text_max);
        if (messages > 5) {
            $remaining.html("<p>Wykorzystano limit znaków.</p>");
        } else {
            $remaining.html("<p>Liczba wiadomości SMS: " + messages + "<br>Zostało " + remaining + " znaków</p>");
        }
        if (textAreaCount === 0 && (messages === 0 || messages ===1)) {
            if (jQuery(this).is(':checked')) {
                $remaining.html("<p>Liczba wiadomości SMS: 1<br>Zostało 160 znaków</p>");
            } else {
                $remaining.html("<p>Liczba wiadomości SMS: 1<br>Zostało 70 znaków</p>");
            }
        }
    });

    jQuery('#playSMS-eventMessage').on('keyup paste', function () {
        if (jQuery('#playSMS-changePolishLettersCheckbox').is(':checked')) {
            var textareaValue = jQuery(this).val();
            changeSpecialCharactersToNormalCharacters(textareaValue);
        }

        var $remaining = jQuery('#playSMS-countMessage'),
            $messages = $remaining.next();
        var chars = this.value.length,
            messages = Math.ceil(chars / text_max),
            remaining = messages * text_max - (chars % (messages * text_max) || messages * text_max);

        if (messages > 5) {
            $remaining.html("<p>Wykorzystano limit znaków.</p>");
        } else {
            $remaining.html("<p>Liczba wiadomości SMS: " + messages + "<br>Zostało " + remaining + " znaków</p>");
        }

        if (chars === 0 && (messages === 0 || messages ===1)) {
            if (jQuery('#playSMS-changePolishLettersCheckbox').prop('checked', false)) {
                $remaining.html("<p>Liczba wiadomości SMS: 1<br>Zostało 70 znaków</p>");
            } else {
                $remaining.html("<p>Liczba wiadomości SMS: 1<br>Zostało 160 znaków</p>");
            }
        }
    });
});


function changeSpecialCharactersToNormalCharacters(textareaValue, elementID) {
    var polishSpecialChars = {};
    polishSpecialChars['ą'] = 'a';
    polishSpecialChars['Ą'] = 'A';
    polishSpecialChars['ć'] = 'c';
    polishSpecialChars['Ć'] = 'C';
    polishSpecialChars['ę'] = 'e';
    polishSpecialChars['Ę'] = 'E';
    polishSpecialChars['ł'] = 'l';
    polishSpecialChars['Ł'] = 'L';
    polishSpecialChars['ń'] = 'n';
    polishSpecialChars['Ń'] = 'N';
    polishSpecialChars['ó'] = 'o';
    polishSpecialChars['Ó'] = 'O';
    polishSpecialChars['ś'] = 's';
    polishSpecialChars['Ś'] = 'S';
    polishSpecialChars['ź'] = 'z';
    polishSpecialChars['Ź'] = 'Z';
    polishSpecialChars['ż'] = 'z';
    polishSpecialChars['Ż'] = 'Z';
    var textAreaNewValue = '';

    if (/^[a-zA-Z0-9- {}]*$/.test(textareaValue) === false) {
        jQuery.each(polishSpecialChars, function (key, value) {
            if (jQuery.isNumeric(elementID)) {
                textAreaNewValue = jQuery('#playSMS-eventMessage-' + elementID).val();
                if (textAreaNewValue.indexOf(key) >= 0) {
                    var regex = new RegExp(key, 'igm');
                    var newMessage = textAreaNewValue.replace(regex, value);
                    jQuery('#playSMS-eventMessage-' + elementID).val(newMessage);
                }
            } else {
                textAreaNewValue = jQuery('#playSMS-eventMessage').val();
                if (textAreaNewValue.indexOf(key) >= 0) {
                    var regex = new RegExp(key, 'igm');
                    var newMessage = textAreaNewValue.replace(regex, value);
                    jQuery('#playSMS-eventMessage').val(newMessage);
                }
            }
        });
    }
}

function loadAllEvents() {
    jQuery.ajax({
        action: 'playSMS_loadEventsAjax',
        type: "POST",
        url: psAjaxObject.ajax_url,
        data: {
            action: 'playSMS_loadEventsAjax'
        },
        success: function (response) {
            jQuery('#playSMS-allEvents').html(response);
        }
    });
}

(function ($, undefined) {
    $.fn.getCursorPosition = function () {
        var el = $(this).get(0);
        var pos = 0;
        if ('selectionStart' in el) {
            pos = el.selectionStart;
        } else if ('selection' in document) {
            el.focus();
            var Sel = document.selection.createRange();
            var SelLength = document.selection.createRange().text.length;
            Sel.moveStart('character', -el.value.length);
            pos = Sel.text.length - SelLength;
        }
        return pos;
    }
})(jQuery);

var apiPassword;
jQuery(document).on('input', '#playSMS-apiPass', function () {
    apiPassword = jQuery(this).val();
});


jQuery(document).on('click', '#playSMS-testApiConnection', function (e) {
    testApiConnection();
});


function testApiConnection() {
    var apiKey = jQuery('#playSMS-apiKey').val();
    var apiPassword = jQuery('#playSMS-apiPass').val();
    jQuery.ajax({
        action: 'playSMS_testApiConnectionAjax',
        type: 'POST',
        datatype: 'json',
        url: psAjaxObject.ajax_url,
        data: {
            action: 'playSMS_testApiConnectionAjax',
            apiKey: apiKey,
            apiPassword: apiPassword

        },
        success: function (response) {
            if (response === 'ERROR') {
                jQuery('#playSMS-connectionInfo').remove();
                jQuery('#plugin-title').after('<div id="ps_connectionInfo" class="notice notice-error is-dismissible"><p>Wystąpił błąd połączenia z API.</p></div>');
                jQuery('#playSMS-apiHeader').prop('disabled', true);
                jQuery('#playSMS-apiHeader').html('');
                jQuery('#playSMS-apiHeader').append(jQuery('<option>', {
                    value: 'noConnection',
                    text: 'Wprowadź klucz API oraz hasło'
                }));
                jQuery('#playSMS-apiKey').css('border-color', 'red');
                jQuery('#playSMS-apiPass').css('border-color', 'red');
                alert("Wystąpił błąd podczas próby połączenia z API, sprawdź klucz oraz hasło.");
            } else {
                var $senderHeadersArray = jQuery.parseJSON(response);
                jQuery('#playSMS-apiHeader').prop('disabled', false);
                jQuery("#playSMS-apiHeader option[value='noConnection']").remove();
                jQuery('#playSMS-connectionInfo').remove();
                jQuery('#plugin-title').after('<div id="ps_connectionInfo" class="notice notice-success is-dismissible"><p>Połączenie z API przebiegło pomyślnie.</p></div>');
                for (var i = 0; i < $senderHeadersArray.length; i++) {
                    if (jQuery("#playSMS-apiHeader option[value='" + $senderHeadersArray[i] + "']").length > 0) {
                        continue;
                    } else {
                        jQuery('<option/>').val($senderHeadersArray[i]).html($senderHeadersArray[i]).appendTo('#playSMS-apiHeader');
                    }
                }
                jQuery('#playSMS-apiKey').css('border-color', 'green');
                jQuery('#playSMS-apiPass').css('border-color', 'green');
                alert("Pola nadawcy pobrane prawidłowo.");
            }
        }
    });
}