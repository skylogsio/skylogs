package security

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
)

func Sign(secret, message string) string {
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write([]byte(message))
	return hex.EncodeToString(mac.Sum(nil))
}

func Verify(secret, message, signature string) bool {
	expected := Sign(secret, message)
	return hmac.Equal([]byte(expected), []byte(signature))
}

