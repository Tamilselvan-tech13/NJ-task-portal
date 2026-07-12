const jwt = require("jsonwebtoken");

const JWT_SECRET = process.env.SESSION_SECRET || "your-secret-key-change-in-production";
const JWT_EXPIRY = "7d";

function generateToken(user) {
  return jwt.sign(
    {
      id: user.id,
      username: user.username,
      email: user.email,
      role: user.role || "user",
    },
    JWT_SECRET,
    { expiresIn: JWT_EXPIRY }
  );
}

function verifyToken(token) {
  try {
    return jwt.verify(token, JWT_SECRET);
  } catch (error) {
    return null;
  }
}

function refreshToken(token) {
  const decoded = verifyToken(token);
  if (!decoded) {
    return null;
  }
  return generateToken(decoded);
}

function decodeToken(token) {
  try {
    return jwt.decode(token);
  } catch (error) {
    return null;
  }
}

module.exports = {
  generateToken,
  verifyToken,
  refreshToken,
  decodeToken,
};
