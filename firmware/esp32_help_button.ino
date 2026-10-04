#include <WiFi.h>
#include <HTTPClient.h>

const char* WIFI_SSID = "YOUR_WIFI_NAME";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";
const char* API_URL = "http://192.168.1.20/Timbal/api.php";
const char* STATION_ID = "LAB-204";
const char* ROOM_NAME = "Lab 2A - Row B";

constexpr uint8_t BUTTON_PIN = 4;
constexpr uint8_t LED_PIN = 2;
constexpr uint8_t BUZZER_PIN = 18;
constexpr unsigned long DEBOUNCE_MS = 45;

bool lastButtonReading = HIGH;
bool stableButtonState = HIGH;
unsigned long lastDebounceAt = 0;

void confirmPress() {
  digitalWrite(LED_PIN, HIGH);
  tone(BUZZER_PIN, 1200, 160);
  delay(180);
  digitalWrite(LED_PIN, LOW);
}

bool sendHelpRequest() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Help request not sent: Wi-Fi is disconnected.");
    return false;
  }

  HTTPClient http;
  http.begin(API_URL);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(4000);
  String body = String("{\"action\":\"create\",\"stationId\":\"") + STATION_ID +
    "\",\"room\":\"" + ROOM_NAME + "\",\"message\":\"Student pressed the lab help button.\",\"source\":\"esp32\"}";
  int statusCode = http.POST(body);
  http.end();

  if (statusCode >= 200 && statusCode < 300) {
    Serial.printf("Help request delivered (HTTP %d).\n", statusCode);
    return true;
  }

  Serial.printf("Help request failed (HTTP %d).\n", statusCode);
  return false;
}

void setup() {
  Serial.begin(115200);
  pinMode(BUTTON_PIN, INPUT_PULLUP);
  pinMode(LED_PIN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(LED_PIN, LOW);

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Connecting to lab Wi-Fi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(350);
    Serial.print('.');
  }
  Serial.printf("\nConnected. API: %s\n", API_URL);
}

void loop() {
  bool reading = digitalRead(BUTTON_PIN);
  if (reading != lastButtonReading) {
    lastDebounceAt = millis();
  }

  if (millis() - lastDebounceAt > DEBOUNCE_MS && reading != stableButtonState) {
    stableButtonState = reading;
    if (stableButtonState == LOW) {
      bool delivered = sendHelpRequest();
      if (delivered) {
        confirmPress();
      } else {
        for (uint8_t flash = 0; flash < 3; ++flash) {
          digitalWrite(LED_PIN, HIGH);
          delay(100);
          digitalWrite(LED_PIN, LOW);
          delay(100);
        }
      }
    }
  }

  lastButtonReading = reading;
  delay(5);
}
