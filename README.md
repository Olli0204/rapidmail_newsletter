# Dokumentation

Mit dem Plugin Rapidmail wird eine Schnittstelle zwischen dem JTL Shop 5 und der Rapidmail API bereitgestellt. Durch diese wird es möglich einen automatisierten Austausch der Newsletterempfänger zwischen Rapimail und ihrem Shop durchzuführen. Dies beinhaltet eine automatisches An- und Abmelden der Nutzer in den Rapidmail-Empfängerlisten.

Im folgenden wird die Einrichtung des Plugins genauer erläutert.

## Zugangsdaten

Im Backend haben sie die Möglichkeit ihre API Zugangsdaten aus ihrem Rapimail Konto zu hinterlegen. Diese können sie mit folgender Anleitung anlegen und auslesen: https://www.rapidmail.de/hilfe/api-zugang-anlegen-und-dokumentation-der-api
Nachdem die Zugangsdaten eingetragen wurden, überprüft das System automatisch ob erfolgreich eine Verbindung hergestellt werden kann.

## Empfängerlisten

Im nächsten Schritt der Einrichtung werden sie aufgefordert, jeweils eine Liste für den Deutschen und Englischen Newsletterversand zu verknüpfen.
Dafür werden die Namen der Listen aus ihrem Rapidmail-Account bereits abgerufen und zur Auswahl bereit gestellt.

Außerdem gibt es die Möglichkeit schnell eine neue Liste direkt aus dem Backend heraus anzulegen. Dazu muss lediglich der Name der neuen Empfängerliste in das Feld eingetragen werden und anschließend auf den Button zum erstellen geklickt werden.

## Import von alten Empfängern

Um auch bereits vorher angemeldete Newsletterempfänger aus dem Onlineshop zu Rapidmail zu übertragen muss lediglich ein Datum eingetragen werden, ab welchem die Newsletterempfänger sich angemeldet haben. Mit dem Datum wird ein Zeitraum definiert (Datum bis zum heutigen Tag). 
Sobald der Import erfolgreich abgeschlossen wurde, wird dies durch eine Meldung mit der Anzahl der importierten Newsletterempfänger bestätigt.

## Laufende Einstellungen

Im letzten Abschnitt werden die für den Betrieb notwendigen Einstellungen getroffen. 

Hier besteht die Möglichkeit, dass die fortan neu angemeldeten Empfänger direkt zu Rapidmail übertragen werden. Die gleiche Funktion gibt es auch für ein automatisches Abmelden.