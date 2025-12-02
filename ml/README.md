# EduSphere ML – At-Risk Prediction

- This folder contains the Python implementation of the logistic regression
  model used by the teacher gradebook.
- `train_at_risk_model.py` connects to the EduSphere MySQL database,
  computes features per student, trains a logistic regression model
  (`scikit-learn`) and writes the learned coefficients into the table
  `risk_logistic_coeffs`.
- The PHP gradebook page reads these coefficients and uses them to compute,
  for each student, the probability of being "at-risk".
